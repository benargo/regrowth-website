<?php

namespace App\Actions\Characters;

use App\Models\Character;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pick which of a player's characters they may log an absence against.
 */
class ResolveSelectableAccountCharacters
{
    use AsAction;

    /**
     * @param  Collection<int, Character>  $accountCharacters
     * @return Collection<int, Character>
     */
    public function handle(Collection $accountCharacters): Collection
    {
        $mains = $accountCharacters->where('is_main', true)->values();

        if ($mains->isNotEmpty()) {
            return $mains;
        }

        return $this->highestLevel($accountCharacters);
    }

    /**
     * Keep the characters at the account's own highest level, which is not necessarily the level cap.
     *
     * @param  Collection<int, Character>  $accountCharacters
     * @return Collection<int, Character>
     */
    private function highestLevel(Collection $accountCharacters): Collection
    {
        $highestLevel = $accountCharacters->max('level');

        if ($highestLevel === null) {
            return $accountCharacters;
        }

        return $accountCharacters->where('level', $highestLevel)->values();
    }
}
