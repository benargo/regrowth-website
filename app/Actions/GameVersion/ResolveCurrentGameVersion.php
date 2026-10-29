<?php

namespace App\Actions\GameVersion;

use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Models\GameVersion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolves the site's current game version: the one stored in the session,
 * otherwise the most recently released version, otherwise the newest version.
 */
class ResolveCurrentGameVersion implements SharesCurrentGameVersionSession
{
    use AsAction;

    public function handle(): ?GameVersion
    {
        $list = ListSelectableGameVersions::run();

        return $list->firstWhere('slug', session(self::SESSION_KEY))
            ?? $list->first(fn (GameVersion $version) => $version->release_date <= now())
            ?? $list->first();
    }
}
