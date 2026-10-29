<?php

namespace App\Observers;

use App\Jobs\Addon\BuildExportFile;
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

        $gameVersionIds = $model instanceof ItemPriority
            ? [Item::whereKey($model->item_id)->value('game_version_id')]
            : [$model->game_version_id, $model->getOriginal('game_version_id')];

        BuildExportFile::scheduleForGameVersionIds($gameVersionIds);
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
