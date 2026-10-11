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
        $this->scheduleAddonExportBuild();
    }

    /**
     * Handle the Item or ItemPriority "deleted" event.
     */
    public function deleted(Item|ItemPriority $model): void
    {
        $this->flushLootCouncilCache();
        $this->scheduleAddonExportBuild();
    }

    /**
     * Flush the cached loot council data.
     */
    private function flushLootCouncilCache(): void
    {
        Cache::tags(['lootcouncil'])->flush();
    }

    /**
     * Schedule a rebuild of the addon export file.
     */
    private function scheduleAddonExportBuild(): void
    {
        BuildAddonExportFile::dispatch()->delay(now()->addSeconds(120));
    }
}
