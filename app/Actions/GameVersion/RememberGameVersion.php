<?php

namespace App\Actions\GameVersion;

use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Stores the given game version slug in the session as the current version.
 */
class RememberGameVersion implements SharesCurrentGameVersionSession
{
    use AsAction;

    public function handle(string $slug): void
    {
        session()->put(self::SESSION_KEY, $slug);
    }
}
