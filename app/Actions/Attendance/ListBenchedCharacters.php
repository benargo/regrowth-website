<?php

namespace App\Actions\Attendance;

use App\Data\Attendance\BenchedCharacterData;
use App\Enums\Presence;
use App\Models\CharacterReport;
use App\Models\GameVersion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lists the characters benched in a game version's reports since a given
 * time, grouped by the report's guild tag name.
 */
class ListBenchedCharacters
{
    use AsAction;

    /**
     * Groups are sorted by tag name, with untagged reports under "Untagged";
     * each group lists a character once, sorted by name.
     *
     * @return Collection<string, Collection<int, BenchedCharacterData>>
     */
    public function handle(GameVersion $gameVersion, CarbonInterface $since): Collection
    {
        return CharacterReport::where('presence', Presence::Benched->value)
            ->whereHas('character')
            ->whereHas('report', fn (Builder $reports) => $reports->whereBelongsTo($gameVersion)->where('start_time', '>=', $since))
            ->with(['character.playableClass', 'report.guildTag'])
            ->get()
            ->unique(fn (CharacterReport $benched): string => "{$benched->report->guild_tag_id}:{$benched->character_id}")
            ->map(fn (CharacterReport $benched): BenchedCharacterData => BenchedCharacterData::fromCharacterReport($benched))
            ->sortBy(fn (BenchedCharacterData $benched): string => $benched->character->name)
            ->groupBy('tag')
            ->sortKeys();
    }
}
