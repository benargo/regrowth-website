<?php

namespace App\Jobs\RaidHelper;

use App\Events\Broadcasts\CompositionChanged;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionData;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionSlotData;
use App\Http\Resources\EventCompositionResource;
use App\Jobs\Concerns\ResolvesCharacterNames;
use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncComposition implements ShouldQueue
{
    use Queueable, ResolvesCharacterNames;

    public function __construct(
        public readonly string $raidHelperEventId,
        public readonly CompositionData $data,
    ) {}

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
                if (Event::where('raid_helper_event_id', $job->raidHelperEventId)->doesntExist()) {
                    return;
                }

                $next($job);
            }
        }];
    }

    /**
     * Execute the job.
     *
     * Slot names are resolved among the event's characters in its game
     * version, since a slot can only hold someone who signed up. A name not on
     * the event yet is skipped; the SyncEvent that attaches it dispatches
     * FetchComposition, which slots it. An event with no game version is left
     * untouched, since an empty slotted set would otherwise detach every
     * slotted character.
     */
    public function handle(): void
    {
        $event = Event::where('raid_helper_event_id', $this->raidHelperEventId)->first();
        $gameVersion = $event->gameVersion;

        if ($gameVersion === null) {
            Log::error('Skipped a Raid-Helper composition for an event with no game version.', [
                'event_id' => $event->id,
                'raid_helper_event_id' => $this->raidHelperEventId,
            ]);

            return;
        }

        $characterIds = $this->resolveCharacterIds(
            $gameVersion,
            array_column($this->data->slots, 'name'),
            $event->characters()->whereBelongsTo($gameVersion)->get(),
            'raidhelper.composition',
        );

        // Build the sync array for slotted characters.
        $slottedSync = [];

        foreach ($this->data->slots as $slot) {
            /** @var CompositionSlotData $slot */
            $characterId = $characterIds->get($slot->name);

            if ($characterId === null) {
                continue;
            }

            $slottedSync[$characterId] = [
                'slot_number' => $slot->slotNumber,
                'group_number' => $slot->groupNumber,
                'signup_status' => $slot->isConfirmed,
                'is_benched' => false,
            ];
        }

        // Sync slotted characters without detaching (preserves benched pivots).
        $event->characters()->syncWithoutDetaching($slottedSync);

        // Detach this version's characters that are no longer slotted and are not benched.
        $toDetach = $event->characters()
            ->whereBelongsTo($gameVersion)
            ->wherePivot('is_benched', false)
            ->whereNotIn('characters.id', array_keys($slottedSync))
            ->pluck('characters.id')
            ->all();

        if (! empty($toDetach)) {
            $event->characters()->detach($toDetach);
        }

        // Broadcast and flush cache.
        $event->load(['characters.playableClass', 'characters.rank', 'raids']);
        $composition = (new EventCompositionResource($event))->resolve();
        broadcast(new CompositionChanged($event->id, $composition));

        Cache::tags(['events'])->flush();
    }
}
