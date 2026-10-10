<?php

namespace App\Jobs\RaidHelper;

use App\Actions\EventBossResolver;
use App\Enums\SignupStatus;
use App\Events\Broadcasts\CompositionChanged;
use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use App\Http\Integrations\RaidHelper\Data\Zones\ZoneData;
use App\Http\Resources\EventCompositionResource;
use App\Jobs\Concerns\ResolvesCharacterNames;
use App\Models\Boss;
use App\Models\Character;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Raid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncEvent implements ShouldQueue
{
    use Queueable, ResolvesCharacterNames;

    private string $timezone;

    /** @var Collection<int, ZoneData> */
    private Collection $zones;

    /** @var Collection<int, Raid> */
    private Collection $raids;

    /** @var Collection<int, Boss> */
    private Collection $bosses;

    private Event $event;

    /** @var array<int, int> */
    private array $signedUpCharacterIds;

    public function __construct(public readonly EventData $data)
    {
        $this->timezone = config('app.timezone', 'UTC');
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new class
        {
            public function handle(object $job, \Closure $next): void
            {
                $allowedChannelIds = config('services.raidhelper.channel_ids', []);

                if (! in_array($job->data->channelId, $allowedChannelIds, strict: true)) {
                    return;
                }

                $next($job);
            }
        }];
    }

    /**
     * Execute the job.
     */
    public function handle(EventBossResolver $eventBossResolver): void
    {
        $this->resolveZones();
        $this->resolveRaids();
        $this->discardUnresolvedZones();
        $this->bosses = $eventBossResolver->fromZones($this->zones, $this->raids);

        $this->upsertEvent();
        $this->syncRaidsAndBosses();
        $this->syncBench();

        $this->broadcastComposition();
        Cache::tags(['events'])->flush();

        FetchComposition::dispatch($this->event->id);
    }

    /**
     * Decode the zones from the event description, in payload order.
     */
    private function resolveZones(): void
    {
        $this->zones = ZoneData::collectFromDescription($this->data->description)
            ->sortBy(fn (ZoneData $zone, int $index): array => [$zone->order ?? PHP_INT_MAX, $index])
            ->values();
    }

    /**
     * Resolve the zones to raids, keyed by id and kept in zone order.
     */
    private function resolveRaids(): void
    {
        $raids = Raid::whereIn('id', $this->zones->pluck('id'))->get()->keyBy('id');

        $this->raids = $this->zones
            ->filter(function (ZoneData $zone) use ($raids): bool {
                $resolved = $raids->get($zone->id);

                if ($resolved?->name === $zone->name) {
                    return true;
                }

                Log::error('SyncEvent: skipping zone that does not match a known raid.', [
                    'zone_id' => $zone->id,
                    'zone_name' => $zone->name,
                ]);

                return false;
            })
            ->mapWithKeys(fn (ZoneData $zone): array => [$zone->id => $raids->get($zone->id)]);
    }

    /**
     * Drop zones whose raid could not be resolved, as they contribute nothing.
     */
    private function discardUnresolvedZones(): void
    {
        $this->zones = $this->zones
            ->filter(fn (ZoneData $zone): bool => $this->raids->has($zone->id))
            ->values();
    }

    /**
     * Create or update the event from the RaidHelper payload.
     */
    private function upsertEvent(): void
    {
        $this->event = Event::updateOrCreate(
            ['raid_helper_event_id' => $this->data->id],
            [
                'title' => $this->data->title,
                'start_time' => $this->data->startTime->setTimezone($this->timezone),
                'end_time' => $this->data->endTime->setTimezone($this->timezone),
                'background_css_class' => $this->raids->values()->firstWhere('background_css_class')?->background_css_class ?? null,
                'color' => $this->data->color,
                'channel_id' => $this->data->channelId,
            ]
        );
    }

    /**
     * Sync the raids, bosses and game version in one transaction, storing
     * contiguous positions based on the payload sequence.
     */
    private function syncRaidsAndBosses(): void
    {
        DB::transaction(function (): void {
            $this->event->raids()->sync(
                $this->zones
                    ->mapWithKeys(fn (ZoneData $zone, int $index): array => [
                        $zone->id => ['sort_order' => $index + 1],
                    ])
                    ->all()
            );

            $this->event->bosses()->sync(
                $this->bosses->mapWithKeys(fn (Boss $boss, int $index): array => [
                    $boss->id => ['sort_order' => $index + 1],
                ])->all()
            );

            $this->event->refreshGameVersion();
        });
    }

    /**
     * Bench the event's sign-ups among the characters of its game version. An
     * event with no game version is left untouched, since an empty sign-up set
     * would otherwise detach every benched character.
     */
    private function syncBench(): void
    {
        $gameVersion = $this->event->gameVersion;

        if ($gameVersion === null) {
            Log::error('Skipped benching Raid-Helper sign-ups for an event with no game version.', [
                'event_id' => $this->event->id,
                'raid_helper_event_id' => $this->data->id,
            ]);

            return;
        }

        $this->resolveSignedUpCharacters($gameVersion);
        $this->attachNewSignedUpCharacters();
        $this->detachBenchedCharactersNoLongerSignedUp($gameVersion);
    }

    /**
     * Resolve the characters of every sign-up who isn't absent, among the
     * event's game version. Bench, Late and Tentative sign-ups are included so
     * they are on the event before an officer slots them. Anyone not placed in
     * the composition is benched.
     */
    private function resolveSignedUpCharacters(GameVersion $gameVersion): void
    {
        $signUpNames = collect($this->data->signUps ?? [])
            ->where('className', '!==', 'Absence')
            ->pluck('name');

        $this->signedUpCharacterIds = $this->resolveCharacterIds(
            $gameVersion,
            $signUpNames,
            Character::whereBelongsTo($gameVersion)->get(),
            'raidhelper.signups',
        )
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Attach signed-up characters as benched, skipping any already on the pivot
     * to avoid overwriting SyncComposition slot data.
     */
    private function attachNewSignedUpCharacters(): void
    {
        $alreadyAttachedIds = $this->event->characters()
            ->whereIn('characters.id', $this->signedUpCharacterIds)
            ->pluck('characters.id')
            ->all();

        foreach (array_diff($this->signedUpCharacterIds, $alreadyAttachedIds) as $characterId) {
            $this->event->characters()->attach($characterId, [
                'slot_number' => null,
                'group_number' => null,
                'signup_status' => SignupStatus::Unconfirmed,
                'is_benched' => true,
            ]);
        }
    }

    /**
     * Detach this game version's benched characters who are no longer in the
     * sign-ups list.
     */
    private function detachBenchedCharactersNoLongerSignedUp(GameVersion $gameVersion): void
    {
        $benchedNoLongerSignedUp = $this->event->characters()
            ->whereBelongsTo($gameVersion)
            ->wherePivot('is_benched', true)
            ->pluck('characters.id')
            ->diff($this->signedUpCharacterIds)
            ->values()
            ->all();

        if (! empty($benchedNoLongerSignedUp)) {
            $this->event->characters()->detach($benchedNoLongerSignedUp);
        }
    }

    /**
     * Broadcast the event's refreshed composition.
     */
    private function broadcastComposition(): void
    {
        $this->event->load(['characters.playableClass', 'characters.rank', 'raids', 'bosses']);
        $composition = (new EventCompositionResource($this->event))->resolve();
        broadcast(new CompositionChanged($this->event->id, $composition));
    }
}
