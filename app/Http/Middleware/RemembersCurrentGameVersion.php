<?php

namespace App\Http\Middleware;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Actions\GameVersion\RememberGameVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RemembersCurrentGameVersion
{
    /**
     * Remember the game version named by a `?game_version=` query-string slug
     * as the user's current one, then redirect to the same URL without it.
     *
     * Applies to non-prefetch GET requests whose query-string value is the slug
     * of a selectable game version; anything else passes through untouched,
     * including values in the request body and numeric ids.
     *
     * The redirect keeps the parameter out of the address bar, so a refresh,
     * or a later reload of deferred props, can't re-select a stale version
     * over the one the user has since switched to.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->query('game_version');

        if (
            ! $request->isMethod('GET')
            || $request->prefetch()
            || ! is_string($slug)
            || ! ListSelectableGameVersions::run()->contains('slug', $slug)
        ) {
            return $next($request);
        }

        RememberGameVersion::run($slug);

        // Keep flashed messages, such as an upload's success notice, for the page we redirect to.
        $request->session()->reflash();

        return redirect()->to($request->fullUrlWithoutQuery('game_version'), 303);
    }
}
