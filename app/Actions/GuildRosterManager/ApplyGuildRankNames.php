<?php

namespace App\Actions\GuildRosterManager;

use App\Actions\GameVersion\SyncGuildRanks;
use App\Models\GameVersion;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Applies the GRM addon's ordered rank names to a game version's guild ranks. Ranks are matched to names by
 * sort-order position and renamed in place, keeping their attendance setting. Names beyond the existing ranks
 * create new ranks that count attendance; ranks beyond the names are deleted. Names are trimmed, and a list
 * containing a blank name is rejected. An empty list changes nothing. No Blizzard guild roster refresh is scheduled.
 */
class ApplyGuildRankNames
{
    use AsAction;

    /**
     * Returns true when any rank was created or deleted.
     *
     * @param  list<string>  $names  In rank order.
     *
     * @throws InvalidArgumentException When a name is blank after trimming.
     */
    public function handle(GameVersion $gameVersion, array $names): bool
    {
        if ($names === []) {
            return false;
        }

        $names = array_map(trim(...), array_values($names));

        if (in_array('', $names, true)) {
            throw new InvalidArgumentException('Guild rank names must not be blank.');
        }

        $existing = $gameVersion->guildRanks()->get()->keyBy('sort_order');

        $ranks = [];

        foreach ($names as $index => $name) {
            $rank = $existing->get($index);

            $ranks[] = [
                'id' => $rank?->id,
                'name' => $name,
                'count_attendance' => $rank?->count_attendance ?? true,
            ];
        }

        $keptIds = array_filter(array_column($ranks, 'id'));

        SyncGuildRanks::run($gameVersion, $ranks, refreshRoster: false);

        return count($keptIds) !== count($ranks) || count($keptIds) !== $existing->count();
    }
}
