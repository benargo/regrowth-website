<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Items\UpdateItemPrioritiesRequest;
use App\Models\LootCouncil\Item;
use App\Services\LootCouncil\LootCouncilCacheService;
use Illuminate\Http\RedirectResponse;

class PrioritiesController extends Controller
{
    public function __construct(
        protected LootCouncilCacheService $cacheService
    ) {}

    /**
     * Update the priorities for a specific loot item.
     */
    public function update(UpdateItemPrioritiesRequest $request, Item $item): RedirectResponse
    {
        $priorities = collect($request->validated('priorities'))
            ->mapWithKeys(fn ($p) => [$p['priority_id'] => ['weight' => $p['weight']]])
            ->all();

        $item->priorities()->sync($priorities);

        $this->cacheService->flush();

        return redirect()->back();
    }
}
