<?php

namespace App\Actions\GuildRosterManager;

use App\Models\GameVersion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Compares the rank names in a game version's current roster revision with the version's guild ranks,
 * position by position, without changing any guild rank.
 */
class CompareRankNames
{
    use AsAction;

    /**
     * List each sort-order position where the game's rank name and the site's differ exactly, including
     * positions only one side has. Returns an empty list when the version has no roster revision.
     *
     * @return list<array{sortOrder: int, gameName: ?string, siteName: ?string}>
     */
    public function handle(GameVersion $gameVersion): array
    {
        $gameNames = $gameVersion->currentRosterRevision?->ranks;

        if ($gameNames === null) {
            return [];
        }

        $gameNames = array_values($gameNames);

        $siteNames = $gameVersion->guildRanks()
            ->orderBy('sort_order')
            ->pluck('name', 'sort_order')
            ->all();

        $positions = collect(array_keys($gameNames))
            ->merge(array_keys($siteNames))
            ->unique()
            ->sort()
            ->values();

        return $positions
            ->map(fn (int $sortOrder): array => [
                'sortOrder' => $sortOrder,
                'gameName' => $gameNames[$sortOrder] ?? null,
                'siteName' => $siteNames[$sortOrder] ?? null,
            ])
            ->reject(fn (array $position): bool => $position['gameName'] === $position['siteName'])
            ->values()
            ->all();
    }
}
