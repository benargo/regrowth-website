<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Models\LootCouncil\Item;
use App\Services\LootCouncil\LootCouncilCacheService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotesController extends Controller
{
    public function __construct(
        protected LootCouncilCacheService $cacheService
    ) {}

    /**
     * Update the officers' notes for a specific loot item.
     */
    public function update(Request $request, Item $item): RedirectResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:5000',
        ]);

        $item->notes = $request->input('notes');
        $item->save();

        $this->cacheService->flush();

        return redirect()->back();
    }
}
