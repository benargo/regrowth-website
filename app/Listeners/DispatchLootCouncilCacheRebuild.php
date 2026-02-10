<?php

namespace App\Listeners;

use App\Events\LootCouncilCacheFlushed;
use App\Jobs\RebuildLootCouncilCacheJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchLootCouncilCacheRebuild implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(LootCouncilCacheFlushed $event): void
    {
        RebuildLootCouncilCacheJob::dispatch();
    }
}
