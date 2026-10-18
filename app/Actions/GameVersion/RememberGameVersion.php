<?php

namespace App\Actions\GameVersion;

use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Stores the given game version slug in the session as the current version.
 */
class RememberGameVersion
{
    use AsAction;

    public function handle(string $slug): void
    {
        session()->put(ResolveCurrentGameVersion::SESSION_KEY, $slug);

        ResolveCurrentGameVersion::forget();
    }
}
