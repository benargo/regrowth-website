<?php

namespace App\Jobs;

use App\Models\GameVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchGuildRosters implements ShouldQueue
{
    use Queueable;

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
     * Dispatch a roster fetch for each Blizzard namespace, using the most
     * recently released game version where several share a namespace.
     * Versions with a future release date are ignored until they launch.
     */
    public function handle(): void
    {
        $gameVersions = GameVersion::query()
            ->whereNotNull('blizzard_namespace')
            ->whereNotNull('realm')
            ->where('release_date', '<=', now())
            ->orderByDesc('release_date')
            ->get()
            ->unique('blizzard_namespace');

        foreach ($gameVersions as $gameVersion) {
            FetchGuildRoster::dispatch($gameVersion->id);
        }
    }
}
