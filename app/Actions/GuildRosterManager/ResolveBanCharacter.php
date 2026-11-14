<?php

namespace App\Actions\GuildRosterManager;

use App\Actions\Characters\MatchCharacterName;
use App\Exceptions\MultipleCharactersFoundException;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Finds the character a GRM ban name refers to, among candidates the caller
 * has already loaded from the game version. A trailing "-{realm}" suffix is
 * removed unless the version is on the Forever namespace. On a version that
 * uses surnames, a name that is a single word matches nothing, and a name
 * matching several characters matches nothing.
 */
class ResolveBanCharacter
{
    use AsAction;

    /**
     * @param  Collection<int, Character>  $candidates
     */
    public function handle(GameVersion $gameVersion, string $name, Collection $candidates): ?Character
    {
        $name = trim($name);

        if ($gameVersion->blizzard_namespace !== BlizzardNamespace::FOREVER && filled($gameVersion->realm)) {
            $suffix = '-'.str($gameVersion->realm)->remove(' ')->toString();

            if (str_ends_with($name, $suffix)) {
                $name = trim(substr($name, 0, -strlen($suffix)));
            }
        }

        if ($gameVersion->uses_surnames && preg_match('/\s/u', $name) !== 1) {
            return null;
        }

        try {
            return MatchCharacterName::run($name, $candidates, $gameVersion->uses_surnames);
        } catch (MultipleCharactersFoundException) {
            return null;
        }
    }
}
