<?php

namespace App\Jobs\GuildRosterManager;

use App\Traits\GuildRosterManager\ResolvesStoragePaths;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Permanently deletes archived GRM CSV uploads older than three months, leaving each game version's latest upload in place.
 */
class PurgeArchivedUploads implements ShouldQueue
{
    use Queueable;
    use ResolvesStoragePaths;

    private const int RETENTION_MONTHS = 3;

    public function handle(#[Storage('local')] Filesystem $disk): void
    {
        $cutoff = now()->subMonths(self::RETENTION_MONTHS)->getTimestamp();

        $expired = collect($disk->allFiles($this->grmArchivePath()))
            ->filter(fn (string $path): bool => str_ends_with($path, '.csv') && $disk->lastModified($path) < $cutoff);

        if ($expired->isEmpty()) {
            return;
        }

        $disk->delete($expired->all());

        Log::info('Purged archived GRM uploads.', ['purged' => $expired->count()]);
    }
}
