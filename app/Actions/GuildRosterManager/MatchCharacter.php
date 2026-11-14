<?php

namespace App\Actions\GuildRosterManager;

use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Finds a game version's character by GRM GUID, then by exact name among characters with no GUID
 * or the same GUID. When the game version uses surnames, the surname must also match exactly and a
 * first name alone never matches; otherwise the surname column is not queried.
 */
class MatchCharacter
{
    use AsAction;

    public function handle(GameVersion $gameVersion, string $guid, string $name, ?string $surname): ?Character
    {
        return Character::where('game_version_id', $gameVersion->id)->where('grm_guid', $guid)->first()
            ?? Character::where('game_version_id', $gameVersion->id)
                ->where('name', $name)
                ->when(
                    $gameVersion->uses_surnames,
                    fn (Builder $query) => $surname === null
                        ? $query->whereNull('surname')
                        : $query->where('surname', $surname),
                )
                ->where(fn (Builder $query) => $query->whereNull('grm_guid')->orWhere('grm_guid', $guid))
                ->first();
    }
}
