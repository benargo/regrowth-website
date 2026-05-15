<?php

namespace App\Listeners;

use App\Contracts\Events\FlushesRaidingCache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class FlushRaidingCache implements ShouldBeUnique, ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(FlushesRaidingCache $event): void
    {
        Cache::tags(['raiding', 'events'])->flush();
    }
}
