<?php

namespace App\Http\Middleware;

use App\Contracts\Http\Middleware\SharesOriginRaidSession;
use App\Models\Item;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RemembersOriginRaid implements SharesOriginRaidSession
{
    /**
     * Apply the remembered origin raid to the request, when it is still
     * relevant to the item being viewed.
     *
     * Written by RemembersCurrentRaid on the raid page itself.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(
            'origin_raid_id',
            $this->rememberedRaidIdFor($request, $request->route('item')),
        );

        return $next($request);
    }

    /**
     * The remembered raid id, but only when this item actually drops in it.
     *
     * The session outlives any single item, so a raid remembered from one item
     * must not be applied to another that does not drop there.
     */
    private function rememberedRaidIdFor(Request $request, ?Item $item): ?int
    {
        $raidId = $request->session()->get(self::SESSION_KEY);

        if (! $raidId || ! $item) {
            return null;
        }

        return $item->raids->contains('id', $raidId) ? (int) $raidId : null;
    }
}
