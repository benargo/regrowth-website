<?php

namespace App\Actions\GuildRosterManager;

use App\Contracts\Actions\GuildRosterManager\AssessesUploadFreshness;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Enums\GuildRosterManager\UploadSource;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterMemberData;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Exceptions\RealmRequiredException;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\RosterEntry;
use App\Models\GuildRosterManager\Upload;
use App\Traits\GuildRosterManager\ResolvesStoragePaths;
use Carbon\Carbon;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Saloon\Exceptions\SaloonException;

/**
 * Compares a game version's latest GRM upload with its live Blizzard guild roster.
 */
class CheckUploadFreshness implements AssessesUploadFreshness
{
    use AsAction;
    use ResolvesStoragePaths;

    public function __construct(
        protected BlizzardConnector $blizzardConnector,
        #[Storage('local')] protected Filesystem $disk,
        #[Config('app.timezone')] protected string $timezone,
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
        $path = $this->grmUploadPath($gameVersion);
        $upload = Upload::where('game_version_id', $gameVersion->id)
            ->accepted()
            ->latest('captured_at')
            ->first();

        if ($upload !== null) {
            $lastModified = $upload->captured_at->copy()->setTimezone($this->timezone);
            $grmRaiderCount = match (true) {
                $upload->source === UploadSource::Desktop => $this->countDesktopUploadRaiders($gameVersion),
                $this->disk->exists($path) => $this->countUploadRaiders($this->disk->get($path)),
                default => 0,
            };
        } else {
            $hasUpload = $this->disk->exists($path);
            $grmRaiderCount = $hasUpload ? $this->countUploadRaiders($this->disk->get($path)) : 0;
            $lastModified = $hasUpload ? Carbon::createFromTimestamp($this->disk->lastModified($path), $this->timezone) : null;
        }

        $blzRaiderCount = $this->countRosterRaiders($gameVersion);

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
     * Count the version's current roster members whose rank name contains
     * "Raider".
     */
    protected function countDesktopUploadRaiders(GameVersion $gameVersion): int
    {
        $ranks = $gameVersion->currentRosterRevision?->ranks ?? [];

        $raiderRankIndexes = collect($ranks)
            ->filter(fn (string $name): bool => stripos($name, 'Raider') !== false)
            ->keys()
            ->all();

        if ($raiderRankIndexes === []) {
            return 0;
        }

        return RosterEntry::where('game_version_id', $gameVersion->id)
            ->where('kind', RosterEntryKind::Member)
            ->whereIn('payload->rankIndex', $raiderRankIndexes)
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
