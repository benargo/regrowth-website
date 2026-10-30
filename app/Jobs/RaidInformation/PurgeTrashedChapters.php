<?php

namespace App\Jobs\RaidInformation;

use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Permanently deletes trashed raid information chapters older than the retention period.
 */
class PurgeTrashedChapters implements ShouldQueue, StoresRaidInformation
{
    use Queueable;

    /**
     * @throws LockTimeoutException When the write lock cannot be acquired within five seconds.
     */
    public function handle(#[Storage(self::DISK)] Filesystem $disk): void
    {
        Cache::lock(self::WRITE_LOCK, 10)->block(5, function () use ($disk): void {
            $cutoff = now()->subDays(self::TRASH_DAYS)->getTimestamp();

            $expired = collect($disk->files(self::TRASH_DIRECTORY))
                ->filter(fn (string $path): bool => str_ends_with($path, '.md') && $disk->lastModified($path) < $cutoff);

            if ($expired->isEmpty()) {
                return;
            }

            foreach ($expired as $path) {
                $disk->delete($path);
            }

            Cache::tags([self::CACHE_TAG])->flush();

            Log::info('Purged trashed raid information chapters.', ['purged' => $expired->count()]);
        });
    }
}
