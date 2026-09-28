<?php

namespace App\Jobs;

use App\Models\GameVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchGuildRosters implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public bool $bypassRateLimit = false,
    ) {}

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['blizzard'];
    }

    /**
     * Dispatch one roster fetch per current Blizzard roster.
     */
    public function handle(): void
    {
        foreach (GameVersion::currentRosters() as $gameVersion) {
            FetchGuildRoster::dispatch($gameVersion->id, $this->bypassRateLimit);
        }
    }
}
