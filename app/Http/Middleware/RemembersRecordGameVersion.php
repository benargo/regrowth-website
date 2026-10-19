<?php

namespace App\Http\Middleware;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Actions\GameVersion\RememberGameVersion;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Contracts\Models\BelongsToGameVersion;
use Closure;
use Illuminate\Http\Request;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class RemembersRecordGameVersion
{
    /**
     * The session key flashed by an explicit game version switch.
     */
    public const string SWITCHED_FLASH_KEY = 'game_version_switched';

    /**
     * Remember the game version that owns the record in the given route
     * parameter as the current one.
     *
     * Applies to GET requests that are neither prefetches nor partial reloads,
     * whose record implements BelongsToGameVersion and is owned by a version
     * other than the current one. It does nothing on the full page request
     * that follows an explicit switch, and removes the switch flag there.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $parameter): Response
    {
        $record = $request->route($parameter);
        $gameVersion = $record instanceof BelongsToGameVersion
            ? ListSelectableGameVersions::run()->find($record->game_version_id)
            : null;

        if (
            $gameVersion === null
            || ! $request->isMethod('GET')
            || $request->prefetch()
            || $request->hasHeader(Header::PARTIAL_COMPONENT)
            || $request->session()->pull(self::SWITCHED_FLASH_KEY, false)
            || ResolveCurrentGameVersion::run()?->slug === $gameVersion->slug
        ) {
            return $next($request);
        }

        RememberGameVersion::run($gameVersion->slug);

        return $next($request);
    }
}
