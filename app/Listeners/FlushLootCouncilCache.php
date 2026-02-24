<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Events\CommentDeleted;
use App\Events\CommentReactionCreated;
use App\Events\CommentReactionDeleted;
use App\Events\CommentUpdated;
use App\Events\ItemPriorityDeleted;
use App\Events\ItemPrioritySaved;
use App\Events\ItemSaved;
use App\Jobs\RebuildLootCouncilCache;
use Illuminate\Support\Facades\Cache;

class FlushLootCouncilCache
{
    /**
     * Handle the event.
     */
    public function handle(CommentCreated|CommentDeleted|CommentUpdated|CommentReactionCreated|CommentReactionDeleted|ItemPriorityDeleted|ItemPrioritySaved|ItemSaved $event): void
    {
        Cache::tags(['lootcouncil'])->flush();

        RebuildLootCouncilCache::dispatch();
    }
}
