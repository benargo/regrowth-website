<?php

namespace App\Actions\GuildRosterManager;

use App\Data\GuildRosterManager\LatestUploadData;
use App\Models\GameVersion;
use App\Traits\GuildRosterManager\ResolvesUploadPath;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemManager;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reads a game version's latest GRM upload from the local disk.
 */
class FindLatestUpload
{
    use AsAction;
    use ResolvesUploadPath;

    public function __construct(
        protected FilesystemManager $storage,
    ) {}

    public function handle(GameVersion $gameVersion): ?LatestUploadData
    {
        $disk = $this->storage->disk('local');
        $path = $this->grmUploadPath($gameVersion);

        if (! $disk->exists($path)) {
            return null;
        }

        return new LatestUploadData(
            lastModified: Carbon::createFromTimestamp($disk->lastModified($path), config('app.timezone')),
            memberCount: $this->countMembers($disk->get($path)),
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
