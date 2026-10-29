<?php

namespace App\Jobs\Concerns;

use App\Actions\Characters\MatchCharacterName;
use App\Exceptions\MultipleCharactersFoundException;
use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

trait ResolvesCharacterNames
{
    /**
     * Resolve each distinct name to the one candidate it matches in the game
     * version. Unknown names are left out silently. A name matching several
     * candidates is left out and logged, because guessing would credit the
     * wrong character.
     *
     * @param  iterable<int, string>  $names
     * @param  Collection<int, Character>  $candidates  Characters of the game version, loaded once by the caller and shared by every name.
     * @param  string  $source  Where the names came from, for the log, such as "raidhelper.signups".
     * @return Collection<string, int> Character IDs keyed by the name as given.
     */
    private function resolveCharacterIds(GameVersion $gameVersion, iterable $names, Collection $candidates, string $source): Collection
    {
        $characterIds = collect();
        $usesSurnames = $gameVersion->uses_surnames;

        foreach (collect($names)->unique() as $name) {
            try {
                $character = MatchCharacterName::run($name, $candidates, $usesSurnames);
            } catch (MultipleCharactersFoundException $exception) {
                Log::error('Skipped a character name that matches more than one character.', [
                    'source' => $source,
                    'game_version_id' => $gameVersion->id,
                    'name' => $name,
                    'character_ids' => $exception->characters->pluck('id')->all(),
                ]);

                continue;
            }

            if ($character !== null) {
                $characterIds->put($name, $character->id);
            }
        }

        return $characterIds;
    }
}
