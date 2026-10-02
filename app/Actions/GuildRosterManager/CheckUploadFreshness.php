<?php

namespace App\Actions\GuildRosterManager;

use App\Contracts\Actions\GuildRosterManager\AssessesUploadFreshness;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterMemberData;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Exceptions\RealmRequiredException;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Models\GameVersion;
use App\Traits\GuildRosterManager\ResolvesUploadPath;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Saloon\Exceptions\SaloonException;

/**
 * Compares a game version's latest GRM upload with its live Blizzard guild roster.
 */
class CheckUploadFreshness implements AssessesUploadFreshness
{
    use AsAction;
    use ResolvesUploadPath;

    public function __construct(
        protected BlizzardConnector $blizzardConnector,
        protected FilesystemManager $storage,
    ) {}

    /**
     * @return array{
     *     gameVersion: array{id: int, title: string, slug: string},
     *     lastModified: Carbon|null,
     *     dataIsStale: bool,
     *     dataIsOutdated: bool,
     *     blzRaiderCount: int|null,
     *     grmRaiderCount: int,
     * }
     */
    public function handle(GameVersion $gameVersion): array
    {
        $disk = $this->storage->disk('local');
        $path = $this->grmUploadPath($gameVersion);
        $hasUpload = $disk->exists($path);

        $grmRaiderCount = $hasUpload ? $this->countUploadRaiders($disk->get($path)) : 0;
        $blzRaiderCount = $this->countRosterRaiders($gameVersion);
        $lastModified = $hasUpload ? Carbon::createFromTimestamp($disk->lastModified($path), config('app.timezone')) : null;

        return [
            'gameVersion' => ['id' => $gameVersion->id, 'title' => $gameVersion->title, 'slug' => $gameVersion->slug],
            'lastModified' => $lastModified,
            'dataIsStale' => $blzRaiderCount !== null
                && abs($blzRaiderCount - $grmRaiderCount) >= self::STALE_THRESHOLD,
            'dataIsOutdated' => $lastModified?->lt(now()->subDays(self::OUTDATED_AFTER_DAYS)) ?? false,
            'blzRaiderCount' => $blzRaiderCount,
            'grmRaiderCount' => $grmRaiderCount,
        ];
    }

    /**
     * Count the members of the version's live roster who hold one of its raider
     * ranks, or null when Blizzard can't return the roster.
     */
    protected function countRosterRaiders(GameVersion $gameVersion): ?int
    {
        try {
            $roster = $this->blizzardConnector->send(new GetGuildRosterRequest(
                $gameVersion->realm_slug,
                $gameVersion->guild_slug,
                $gameVersion->blizzard_namespace,
            ))->dto();
        } catch (BlizzardRequestException|SaloonException|RealmRequiredException $exception) {
            Log::warning('Skipped GRM freshness roster check: the guild roster could not be fetched.', [
                'game_version_id' => $gameVersion->id,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        $raiderRankPositions = $gameVersion->guildRanks()
            ->whereLike('name', '%Raider%')
            ->pluck('sort_order');

        return collect($roster->members)
            ->filter(fn (GuildRosterMemberData $member): bool => $raiderRankPositions->contains($member->rank))
            ->count();
    }

    /**
     * Count the rows of a GRM CSV export whose rank column mentions "Raider".
     */
    protected function countUploadRaiders(string $csv): int
    {
        $lines = preg_split('/\r\n|\r|\n/', $csv);
        $header = str_getcsv(array_shift($lines));

        $rankColumnIndex = collect($header)
            ->search(fn (string $column): bool => stripos($column, 'Rank') !== false);

        if ($rankColumnIndex === false) {
            return 0;
        }

        return collect($lines)
            ->map(fn (string $line): array => str_getcsv($line))
            ->filter(fn (array $columns): bool => isset($columns[$rankColumnIndex])
                && stripos($columns[$rankColumnIndex], 'Raider') !== false)
            ->count();
    }
}
