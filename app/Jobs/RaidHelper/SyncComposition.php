<?php

namespace App\Jobs\RaidHelper;

use App\Events\Broadcasts\CompositionChanged;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionData;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionSlotData;
use App\Http\Resources\EventCompositionResource;
use App\Models\Character;
use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class SyncComposition implements ShouldQueue
{
    use Queueable;

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
     */
    public function handle(): void
    {
        $event = Event::where('raid_helper_event_id', $this->raidHelperEventId)->first();

        // Build the sync array for slotted characters.
        $slottedSync = [];

        $allSlotNames = array_column($this->data->slots, 'name');
        $charactersByName = Character::whereIn('name', $allSlotNames)->get()->keyBy('name');

        foreach ($this->data->slots as $slot) {
            /** @var CompositionSlotData $slot */
            $character = $charactersByName->get($slot->name);

            if (! $character) {
                continue;
            }

            $slottedSync[$character->id] = [
                'slot_number' => $slot->slotNumber,
                'group_number' => $slot->groupNumber,
                'signup_status' => $slot->isConfirmed,
                'is_benched' => false,
            ];
        }

        // Sync slotted characters without detaching (preserves benched pivots).
        $event->characters()->syncWithoutDetaching($slottedSync);

        // Detach characters that are no longer slotted and are not benched.
        $slottedIds = array_keys($slottedSync);

        $toDetach = $event->characters()
            ->wherePivot('is_benched', false)
            ->whereNotIn('characters.id', $slottedIds)
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
