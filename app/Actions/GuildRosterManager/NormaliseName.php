<?php

namespace App\Actions\GuildRosterManager;

use App\Models\Character;
use App\Models\GameVersion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Removes the game version's realm suffix ("-{realm without spaces}") from a GRM name, matching
 * only that exact suffix, then splits the result into a name and surname.
 */
class NormaliseName
{
    use AsAction;

    /**
     * @return array{name: string, surname: ?string}
     */
    public function handle(GameVersion $gameVersion, string $grmName): array
    {
        return Character::splitName($gameVersion->withoutRealmSuffix($grmName), $gameVersion->uses_surnames);
    }
}
