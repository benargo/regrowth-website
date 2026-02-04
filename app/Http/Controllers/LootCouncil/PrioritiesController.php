<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Items\UpdateItemPrioritiesRequest;
use App\Models\LootCouncil\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class PrioritiesController extends Controller
{
    /**
     * Update the priorities for a specific loot item.
     */
    public function update(UpdateItemPrioritiesRequest $request, Item $item): RedirectResponse
    {
        $priorities = collect($request->validated('priorities'))
            ->mapWithKeys(fn ($p) => [$p['priority_id'] => ['weight' => $p['weight']]])
            ->all();

        $item->priorities()->sync($priorities);

        if ($item->boss_id) {
            Cache::forget("loot_items.boss_{$item->boss_id}");
        } else {
            Cache::forget("loot_items.trash_raid_{$item->raid_id}");
        }

        return redirect()->back();
    }
}
