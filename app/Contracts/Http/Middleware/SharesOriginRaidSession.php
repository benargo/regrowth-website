<?php

namespace App\Contracts\Http\Middleware;

interface SharesOriginRaidSession
{
    /**
     * The session key holding the raid the user is currently browsing.
     */
    public const SESSION_KEY = 'origin_raid_id';
}
