<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;

trait QueriesLootCouncilCache
{
    /**
     * @return EloquentCollection<Raid>
     */
    protected function getRaidsForPhase(Phase $phase): EloquentCollection
    {
        return Raid::hydrate(
            Cache::tags(['db', 'lootcouncil'])->remember("phases:#{$phase->id}:raids", now()->addYear(), function () use ($phase) {
                return $phase->raids()->get()->toArray();
            })
        );
    }
}
