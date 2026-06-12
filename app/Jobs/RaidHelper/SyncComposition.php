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
use Illuminate\Support\Facades\Log;

class SyncComposition implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $raidHelperEventId,
        public readonly CompositionData $data,
    ) {}

    public function handle(): void
    {
        $event = Event::where('raid_helper_event_id', $this->raidHelperEventId)->first();

        if (! $event) {
            Log::warning('SyncComposition: event not found.', ['raid_helper_event_id' => $this->raidHelperEventId]);

            return;
        }

        // Build the sync array for slotted characters.
        $slottedSync = [];

        $allSlotNames = array_map(fn (CompositionSlotData $slot) => $slot->name, $this->data->slots);
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
                'is_confirmed' => $slot->isConfirmed,
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
