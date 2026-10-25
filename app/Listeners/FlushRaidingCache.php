<?php

namespace App\Listeners;

use App\Contracts\Events\FlushesRaidingCache;
use Illuminate\Support\Facades\Cache;

/**
 * Flushes the raiding and events cache tags.
 */
class FlushRaidingCache
{
    /**
     * Handle the event.
     */
    public function handle(FlushesRaidingCache $event): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        Cache::tags(['raiding', 'events'])->flush();
    }
}
