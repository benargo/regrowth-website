<?php

namespace App\Actions\GuildRosterManager;

use App\Models\Character;
use App\Models\GuildRosterManager\Ban;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Links the game version's GRM bans that carry the character's GUID to the character, and fills
 * the user on those bans from the character. Bans linked to another character are never changed,
 * and no ban is deleted.
 */
class LinkBansToCharacter
{
    use AsAction;

    public function handle(Character $character): void
    {
        if ($character->grm_guid === null) {
            return;
        }

        $bans = fn (): Builder => Ban::where('game_version_id', $character->game_version_id)
            ->where('grm_guid', $character->grm_guid);

        $bans()->whereNull('character_id')->update(['character_id' => $character->id]);

        if ($character->user_id !== null) {
            $bans()->where('character_id', $character->id)->whereNull('user_id')->update(['user_id' => $character->user_id]);
        }
    }
}
