<?php

namespace App\Listeners;

use App\Contracts\Events\FlushesRaidingCache;
use Illuminate\Support\Facades\Cache;

class FlushRaidingCache
{
    /**
     * Handle the event.
     */
    public function handle(FlushesRaidingCache $event): void
    {
        Cache::tags(['raiding', 'events'])->flush();
    }
}
