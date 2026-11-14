<?php

namespace App\Actions\GuildRosterManager;

use App\Models\GameVersion;
use App\Models\PlayableRace;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Finds the game version's playable race matching a GRM race token, case-insensitively and
 * ignoring non-letters, with "Scourge" read as "Undead", without calling Blizzard.
 */
class ResolvePlayableRace
{
    use AsAction;

    public function handle(GameVersion $gameVersion, string $token): ?PlayableRace
    {
        $token = str(['Scourge' => 'Undead'][$token] ?? $token)->lower();

        return $gameVersion->playableRaces->first(
            fn (PlayableRace $race): bool => str($race->name)->replaceMatches('/[^A-Za-z]/', '')->lower()->exactly($token),
        );
    }
}
