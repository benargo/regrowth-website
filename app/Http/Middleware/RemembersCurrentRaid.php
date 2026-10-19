<?php

namespace App\Http\Middleware;

use App\Contracts\Http\Middleware\SharesOriginRaidSession;
use App\Models\Boss;
use App\Models\Raid;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RemembersCurrentRaid implements SharesOriginRaidSession
{
    /**
     * Remember the raid the user is currently browsing: the raid in the given
     * route parameter, or the raid of the boss in it.
     *
     * Written on raid and boss pages, and read back by RemembersOriginRaid on item pages.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $parameter = 'raid'): Response
    {
        /** @var Raid|Boss $record */
        $record = $request->route($parameter);

        $request->session()->put(self::SESSION_KEY, $record instanceof Boss ? $record->raid_id : $record->id);

        return $next($request);
    }
}
