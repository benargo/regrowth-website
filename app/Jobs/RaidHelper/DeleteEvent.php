<?php

namespace App\Jobs\RaidHelper;

use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeleteEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $raidHelperEventId) {}

    public function handle(): void
    {
        $event = Event::where('raid_helper_event_id', $this->raidHelperEventId)->first();

        if (! $event) {
            Log::notice('DeleteEvent: event not found, nothing to delete.', [
                'raid_helper_event_id' => $this->raidHelperEventId,
            ]);

            return;
        }

        $event->delete();

        Cache::tags(['events'])->flush();
    }
}
