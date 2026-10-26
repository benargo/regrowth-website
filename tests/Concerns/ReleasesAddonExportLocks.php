<?php

namespace Tests\Concerns;

use App\Jobs\BuildAddonExportFile;
use App\Models\GameVersion;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Cache;

trait ReleasesAddonExportLocks
{
    /**
     * Release the unique locks held by addon export builds queued while building fixtures.
     */
    protected function releaseAddonExportLocks(): void
    {
        foreach (GameVersion::pluck('id') as $gameVersionId) {
            Cache::lock(UniqueLock::getKey(new BuildAddonExportFile($gameVersionId)))->forceRelease();
        }
    }
}
