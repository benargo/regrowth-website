<?php

namespace App\Observers;

use App\Jobs\BuildAddonExportFile;
use App\Models\Item;
use App\Models\ItemPriority;
use Illuminate\Support\Facades\Cache;

class ItemObserver
{
    /**
     * Handle the Item or ItemPriority "saved" event.
     */
    public function saved(Item|ItemPriority $model): void
    {
        $this->flushLootCouncilCache();
        BuildAddonExportFile::schedule();
    }

    /**
     * Handle the Item or ItemPriority "deleted" event.
     */
    public function deleted(Item|ItemPriority $model): void
    {
        $this->saved($model);
    }

    /**
     * Flush the cached loot council data.
     */
    private function flushLootCouncilCache(): void
    {
        Cache::tags(['lootcouncil'])->flush();
    }
}
