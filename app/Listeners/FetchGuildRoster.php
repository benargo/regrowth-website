<?php

namespace App\Listeners;

use App\Events\AddonSettingsProcessed;
use App\Events\GrmUploadProcessed;
use App\Jobs\FetchGuildRoster as FetchGuildRosterJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class FetchGuildRoster implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(GrmUploadProcessed|AddonSettingsProcessed $event): void
    {
        dispatch(new FetchGuildRosterJob);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['blizzard', 'guild', 'roster'];
    }
}
