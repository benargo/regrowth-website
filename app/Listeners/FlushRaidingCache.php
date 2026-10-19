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
        $this->flush();
    }

    /**
     * Flush every cached entry tagged `raiding` and `events`.
     */
    public function flush(): void
    {
        Cache::tags(['raiding', 'events'])->flush();
    }
}
