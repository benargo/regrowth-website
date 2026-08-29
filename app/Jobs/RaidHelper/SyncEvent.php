<?php

namespace App\Jobs\RaidHelper;

use App\Actions\EventBossResolver;
use App\Enums\SignupStatus;
use App\Events\Broadcasts\CompositionChanged;
use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use App\Http\Integrations\RaidHelper\Data\Zones\ZoneData;
use App\Http\Resources\EventCompositionResource;
use App\Models\Boss;
use App\Models\Character;
use App\Models\Event;
use App\Models\Raid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncEvent implements ShouldQueue
{
    use Queueable;

    private string $timezone;

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
     * Resolve the zones to raids, keyed by id and kept in zone order.
     *
     * A zone must match both the id and the name to resolve, guarding against a
     * stale payload pointing at a raid that has since been replaced. Zones that
     * do not resolve are skipped and logged rather than failing the sync.
     *
     * @param  Collection<int, ZoneData>  $zones
     * @return Collection<int, Raid>
     */
    private function resolveRaids(Collection $zones): Collection
    {
        $raids = Raid::whereIn('id', $zones->pluck('id'))->get()->keyBy('id');

        return $zones
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
     * Execute the job.
     */
    public function handle(EventBossResolver $eventBossResolver): void
    {
        // Decode the zones from the event description, in payload order.
        $zones = ZoneData::collectFromDescription($this->data->description)
            ->sortBy(fn (ZoneData $zone, int $index): array => [$zone->order ?? PHP_INT_MAX, $index])
            ->values();

        $raids = $this->resolveRaids($zones);

        // Zones whose raid could not be resolved contribute nothing.
        $zones = $zones->filter(fn (ZoneData $zone): bool => $raids->has($zone->id))->values();

        $bosses = $eventBossResolver->fromZones($zones, $raids);

        // Upsert the event.
        $event = Event::updateOrCreate(
            ['raid_helper_event_id' => $this->data->id],
            [
                'title' => $this->data->title,
                'start_time' => $this->data->startTime->setTimezone($this->timezone),
                'end_time' => $this->data->endTime->setTimezone($this->timezone),
                'background_css_class' => $raids->values()->firstWhere('background_css_class')?->background_css_class ?? null,
                'color' => $this->data->color,
                'channel_id' => $this->data->channelId,
            ]
        );

        // Sync the raids and bosses together so the two cannot diverge. Both
        // are written with explicit, contiguous positions derived from the
        // payload sequence — the payload's own `order` values are only a
        // sorting hint and are never stored verbatim.
        DB::transaction(function () use ($event, $zones, $bosses): void {
            $event->raids()->sync(
                $zones->values()
                    ->mapWithKeys(fn (ZoneData $zone, int $index): array => [
                        $zone->id => ['sort_order' => $index + 1],
                    ])
                    ->all()
            );

            $event->bosses()->sync(
                $bosses->mapWithKeys(fn (Boss $boss, int $index): array => [
                    $boss->id => ['sort_order' => $index + 1],
                ])->all()
            );
        });

        // Sync benched characters from sign-ups (all signed-up, non-absent players not in comp are benched).
        $characterSync = [];

        $signUps = collect($this->data->signUps ?? [])
            ->whereNotIn('className', ['Absence', 'Late', 'Tentative']);

        Character::whereIn('name', $signUps->pluck('name'))->get()
            ->each(function (Character $character) use (&$characterSync): void {
                $characterSync[$character->id] = [
                    'slot_number' => null,
                    'group_number' => null,
                    'signup_status' => SignupStatus::Unconfirmed,
                    'is_benched' => true,
                ];
            });

        // Only attach characters not already on the pivot to avoid overwriting SyncComposition slot data.
        $signedUpCharacterIds = array_keys($characterSync);

        $alreadyAttachedIds = $event->characters()
            ->whereIn('characters.id', $signedUpCharacterIds)
            ->pluck('characters.id')
            ->all();

        $newCharacterIds = array_diff($signedUpCharacterIds, $alreadyAttachedIds);

        foreach ($newCharacterIds as $characterId) {
            $event->characters()->attach($characterId, $characterSync[$characterId]);
        }

        // Detach benched characters who are no longer in the sign-ups list.
        // Slotted characters (is_benched=false) are managed by SyncComposition and must not be touched.
        $benchedNoLongerSignedUp = $event->characters()
            ->wherePivot('is_benched', true)
            ->pluck('characters.id')
            ->diff($signedUpCharacterIds)
            ->values()
            ->all();

        if (! empty($benchedNoLongerSignedUp)) {
            $event->characters()->detach($benchedNoLongerSignedUp);
        }

        // Broadcast and flush cache.
        $event->load(['characters.playableClass', 'characters.rank', 'raids', 'bosses']);
        $composition = (new EventCompositionResource($event))->resolve();
        broadcast(new CompositionChanged($event->id, $composition));

        Cache::tags(['events'])->flush();

        FetchComposition::dispatch($event->id);
    }
}
