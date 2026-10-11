<?php

namespace App\Actions\GameVersion;

use App\Models\GameVersion;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Picks the game version a page shows from the versions it can select,
 * preferring the one the user last chose.
 */
class ResolveCurrentGameVersion
{
    use AsAction;

    public const string SESSION_KEY = 'current_game_version';

    /**
     * @param  Collection<int, GameVersion>  $selectable  In the page's display order.
     */
    public function handle(Collection $selectable): ?GameVersion
    {
        $defaultRoster = GameVersion::defaultRoster();

        return $selectable->firstWhere('slug', session(self::SESSION_KEY))
            ?? $selectable->first(fn (GameVersion $gameVersion): bool => $gameVersion->is($defaultRoster))
            ?? $selectable->first();
    }
}
