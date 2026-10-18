<?php

namespace App\Actions\GameVersion;

use App\Models\GameVersion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolves the site's current game version: the one stored in the session,
 * otherwise the most recently released version, otherwise the newest version.
 * The result is memoised for the duration of the request.
 */
class ResolveCurrentGameVersion
{
    use AsAction;

    public const string SESSION_KEY = 'current_game_version';

    public function handle(): ?GameVersion
    {
        $attributes = request()->attributes;

        if (! $attributes->has(self::class)) {
            $list = ListSelectableGameVersions::run();

            $attributes->set(self::class, $list->firstWhere('slug', session(self::SESSION_KEY))
                ?? $list->first(fn (GameVersion $version) => $version->release_date <= now())
                ?? $list->first());
        }

        return $attributes->get(self::class);
    }

    /**
     * Clears the memoised current version for this request.
     */
    public static function forget(): void
    {
        request()->attributes->remove(self::class);
    }
}
