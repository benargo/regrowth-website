<?php

namespace App\Jobs\RaidHelper;

use App\Events\Broadcasts\CompositionChanged;
use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use App\Http\Resources\EventCompositionResource;
use App\Models\Character;
use App\Models\Event;
use App\Models\Raid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
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
     * Execute the job.
     */
    public function handle(): void
    {
        // Decode raids from the event description.
        $raidsString = str($this->data->description)
            ->after("-# Do not edit below this line...\n")
            ->trim();

        $raids = collect();

        if ($raidsString->isJson()) {
            $decoded = json_decode(stripslashes($raidsString), true);

            foreach ($decoded as $row) {
                if (! Arr::hasAll($row, ['id', 'name'])) {
                    Log::error('SyncEvent: skipping raid row with missing keys.', ['row' => $row]);

                    continue;
                }

                $query = Raid::where('id', Arr::get($row, 'id'))->where('name', Arr::get($row, 'name'));

                $raid = $query->first();

                if ($raid) {
                    $raids->push($raid);
                }
            }
        }

        // Upsert the event.
        $event = Event::updateOrCreate(
            ['raid_helper_event_id' => $this->data->id],
            [
                'title' => $this->data->title,
                'start_time' => $this->data->startTime->setTimezone($this->timezone),
                'end_time' => $this->data->endTime->setTimezone($this->timezone),
                'background_css_class' => $raids->firstWhere('background_css_class')?->background_css_class ?? null,
                'color' => $this->data->color,
                'channel_id' => $this->data->channelId,
            ]
        );

        // Sync associated raids.
        $event->raids()->sync($raids->pluck('id')->all());

        // Sync benched characters from sign-ups (all signed-up, non-absent players not in comp are benched).
        $characterSync = [];

        $signUps = collect($this->data->signUps ?? [])
            ->whereNotIn('className', ['Absence', 'Late', 'Tentative']);

        Character::whereIn('name', $signUps->pluck('name'))->get()
            ->each(function (Character $character) use (&$characterSync): void {
                $characterSync[$character->id] = [
                    'slot_number' => null,
                    'group_number' => null,
                    'is_confirmed' => false,
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
        $event->load(['characters.playableClass', 'characters.rank', 'raids']);
        $composition = (new EventCompositionResource($event))->resolve();
        broadcast(new CompositionChanged($event->id, $composition));

        Cache::tags(['events'])->flush();

        FetchComposition::dispatch($event->id);
    }
}
