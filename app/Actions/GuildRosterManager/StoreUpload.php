<?php

namespace App\Actions\GuildRosterManager;

use App\Jobs\ProcessGrmUpload;
use App\Models\GameVersion;
use App\Models\User;
use App\Traits\GuildRosterManager\ResolvesUploadPath;
use Illuminate\Filesystem\FilesystemManager;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Saves an officer's GRM export as the game version's latest upload, keeps a
 * timestamped archive copy, and queues it for processing.
 */
class StoreUpload
{
    use AsAction;
    use ResolvesUploadPath;

    public function __construct(
        protected FilesystemManager $storage,
    ) {}

    /**
     * @param  array{delimiter: string, headers: array<int, string>, rows: array<int, array<string, string>>}  $parsedCsv
     */
    public function handle(GameVersion $gameVersion, string $csv, array $parsedCsv, User $uploader): void
    {
        $disk = $this->storage->disk('local');
        $timestamp = now()->format('Y-m-d_H-i-s');

        $disk->put("grm/archives/{$gameVersion->slug}/{$timestamp}.csv", $csv);
        $disk->put($this->grmUploadPath($gameVersion), $csv);

        // Progress is broadcast live on the uploading user's private channel.
        ProcessGrmUpload::dispatch($parsedCsv, $uploader->id, $gameVersion->id)->withoutDelay();
    }
}
