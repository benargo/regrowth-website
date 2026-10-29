<?php

namespace App\Jobs\Addon;

use App\Models\GameVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildExportFiles implements ShouldQueue
{
    use Queueable;

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['regrowth-addon'];
    }

    /**
     * Dispatch one addon export build per current roster.
     */
    public function handle(): void
    {
        foreach (GameVersion::currentRosters() as $gameVersion) {
            BuildExportFile::dispatch($gameVersion->id);
        }
    }
}
