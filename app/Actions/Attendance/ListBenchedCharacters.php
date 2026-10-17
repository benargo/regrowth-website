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
            ->with(['character.playableClass.media', 'report.guildTag'])
            ->get()
            ->map(fn (CharacterReport $benched): BenchedCharacterData => BenchedCharacterData::fromCharacterReport($benched))
            ->groupBy('tag')
            ->map(fn (Collection $group): Collection => $group
                ->unique(fn (BenchedCharacterData $benched): int => $benched->character->id)
                ->sortBy(fn (BenchedCharacterData $benched): string => $benched->character->name)
                ->values())
            ->sortKeys();
    }
}
