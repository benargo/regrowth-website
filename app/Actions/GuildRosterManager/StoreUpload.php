<?php

namespace App\Actions\GuildRosterManager;

use App\Enums\GuildRosterManager\UploadOutcome;
use App\Enums\GuildRosterManager\UploadSource;
use App\Jobs\GuildRosterManager\ProcessUpload;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use App\Traits\GuildRosterManager\ResolvesStoragePaths;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Saves an officer's GRM export as the game version's latest upload, keeps a
 * timestamped archive copy, records the upload, and queues it for processing.
 */
class StoreUpload
{
    use AsAction;
    use ResolvesStoragePaths;

    public function __construct(
        #[Storage('local')] protected Filesystem $disk,
    ) {}

    /**
     * @param  array{delimiter: string, headers: array<int, string>, rows: array<int, array<string, string>>}  $parsedCsv
     */
    public function handle(GameVersion $gameVersion, string $csv, array $parsedCsv, User $uploader): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');

        $archivePath = "{$this->grmArchivePath($gameVersion)}/{$timestamp}.csv";

        $this->disk->put($archivePath, $csv);
        $this->disk->put($this->grmUploadPath($gameVersion), $csv);

        Upload::create([
            'game_version_id' => $gameVersion->id,
            'user_id' => $uploader->id,
            'source' => UploadSource::Manual,
            'outcome' => UploadOutcome::Processed,
            'captured_at' => now(),
            'member_count' => count($parsedCsv['rows']),
            'storage_path' => $archivePath,
            'idempotency_key' => null,
        ]);

        // Progress is broadcast live on the uploading user's private channel.
        ProcessUpload::dispatch($parsedCsv, $uploader->id, $gameVersion->id)->withoutDelay();
    }
}
