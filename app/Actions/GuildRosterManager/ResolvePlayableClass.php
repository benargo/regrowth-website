<?php

namespace App\Actions\GuildRosterManager;

use App\Models\GameVersion;
use App\Models\PlayableClass;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Finds the game version's playable class whose upper-cased, letters-only name equals a GRM class
 * token, without calling Blizzard.
 */
class ResolvePlayableClass
{
    use AsAction;

    public function handle(GameVersion $gameVersion, string $token): ?PlayableClass
    {
        return $gameVersion->playableClasses->first(
            fn (PlayableClass $class): bool => str($class->name)->replaceMatches('/[^A-Za-z]/', '')->upper()->exactly($token),
        );
    }
}
