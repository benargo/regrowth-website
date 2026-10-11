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
     * current one.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->filled('game_version') && $request->header('Purpose') !== 'prefetch') {
            $request->session()->put(
                ResolveCurrentGameVersion::SESSION_KEY,
                $request->string('game_version')->toString(),
            );
        }

        return $next($request);
    }
}
