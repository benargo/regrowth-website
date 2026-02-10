<?php

namespace App\Services\LootCouncil;

use App\Events\LootCouncilCacheFlushed;
use Illuminate\Support\Facades\Cache;

class LootCouncilCacheService
{
    /**
     * Flush the LootCouncil cache and dispatch rebuild event.
     */
    public function flush(): void
    {
        Cache::tags(['lootcouncil'])->flush();

        LootCouncilCacheFlushed::dispatch();
    }
}
