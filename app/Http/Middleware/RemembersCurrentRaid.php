<?php

namespace App\Http\Middleware;

use App\Contracts\Http\Middleware\SharesOriginRaidSession;
use App\Models\Raid;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RemembersCurrentRaid implements SharesOriginRaidSession
{
    /**
     * Remember the raid the user is currently browsing.
     *
     * Written here, on the raid page itself, and read back by
     * RemembersOriginRaid on item pages — simpler and safer than trying to
     * reverse-engineer the previous page from a Referer header.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Raid $raid */
        $raid = $request->route('raid');

        $request->session()->put(self::SESSION_KEY, $raid->id);

        return $next($request);
    }
}
