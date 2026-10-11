<?php

namespace App\Http\Middleware;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RemembersCurrentGameVersion
{
    /**
     * Remember the game version named by `?game_version=` as the user's
     * current one, then redirect to the same URL without it.
     *
     * The redirect keeps the parameter out of the address bar, so a refresh,
     * or a later reload of deferred props, can't re-select a stale version
     * over the one the user has since switched to.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->filled('game_version') || $request->prefetch()) {
            return $next($request);
        }

        $request->session()->put(
            ResolveCurrentGameVersion::SESSION_KEY,
            $request->string('game_version')->toString(),
        );

        // Keep flashed messages, such as an upload's success notice, for the page we redirect to.
        $request->session()->reflash();

        return redirect()->to($request->fullUrlWithoutQuery('game_version'), 303);
    }
}
