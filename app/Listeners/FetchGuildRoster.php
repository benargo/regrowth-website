<?php

namespace App\Listeners;

use App\Events\AddonSettingsProcessed;
use App\Jobs\FetchGuildRosters;
use Illuminate\Contracts\Queue\ShouldQueue;

class FetchGuildRoster implements ShouldQueue
{
    /**
     * Fetch the roster for every game version when addon settings change.
     */
    public function handle(AddonSettingsProcessed $event): void
    {
        FetchGuildRosters::dispatch();
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['blizzard'];
    }
}
