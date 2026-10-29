<?php

namespace App\Http\Middleware;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Actions\GameVersion\RememberGameVersion;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Contracts\Http\Middleware\SharesGameVersionSwitchFlash;
use App\Contracts\Models\BelongsToGameVersion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class RemembersRecordGameVersion implements SharesGameVersionSwitchFlash
{
    /**
     * Remember the routed record's game version, or redirect to the section index right after a switch.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $parameter, string $indexRoute): Response
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        if ($request->prefetch()) {
            return $next($request);
        }

        if ($request->hasHeader(Header::PARTIAL_COMPONENT)) {
            return $next($request);
        }

        $record = $request->route($parameter);
        $gameVersion = $record instanceof BelongsToGameVersion
            ? ListSelectableGameVersions::run()->find($record->game_version_id)
            : null;

        if ($gameVersion === null) {
            return $next($request);
        }

        $switched = $request->session()->pull(self::SWITCHED_FLASH_KEY, false);

        if (ResolveCurrentGameVersion::run()?->slug === $gameVersion->slug) {
            return $next($request);
        }

        if ($switched) {
            $section = Str::before($request->route()->getName(), '.');

            return redirect()->route("{$section}.{$indexRoute}");
        }

        RememberGameVersion::run($gameVersion->slug);

        return $next($request);
    }
}
