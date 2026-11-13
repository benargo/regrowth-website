<?php

namespace App\Actions\GuildRosterManager;

use App\Data\GuildRosterManager\LatestUploadData;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Upload;
use App\Traits\GuildRosterManager\ResolvesStoragePaths;
use Carbon\Carbon;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reads a game version's latest GRM upload from the recorded uploads, falling
 * back to the CSV file on the local disk.
 */
class FindLatestUpload
{
    use AsAction;
    use ResolvesStoragePaths;

    public function __construct(
        #[Storage('local')] protected Filesystem $disk,
        #[Config('app.timezone')] protected string $timezone,
    ) {}

    public function handle(GameVersion $gameVersion): ?LatestUploadData
    {
        $upload = Upload::where('game_version_id', $gameVersion->id)
            ->accepted()
            ->latest('captured_at')
            ->first();

        if ($upload !== null) {
            return new LatestUploadData(
                lastModified: $upload->captured_at->copy()->setTimezone($this->timezone),
                memberCount: $upload->member_count,
            );
        }

        $path = $this->grmUploadPath($gameVersion);

        if (! $this->disk->exists($path)) {
            return null;
        }

        return new LatestUploadData(
            lastModified: Carbon::createFromTimestamp($this->disk->lastModified($path), $this->timezone),
            memberCount: $this->countMembers($this->disk->get($path)),
        );
    }

    /**
     * Count the CSV's member rows. Each "Export Next" chunk an officer pastes
     * in repeats the header row, so those are skipped along with blank lines.
     */
    protected function countMembers(string $csv): int
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', $csv))
            ->map(fn (string $line): string => trim($line));

        $header = $lines->shift();

        return $lines
            ->reject(fn (string $line): bool => $line === '' || $line === $header)
            ->count();
    }
}
