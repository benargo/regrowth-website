<?php

namespace App\Http\Controllers\LootCouncil;

use App\Models\LootCouncil\Item;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class NotesController extends Controller
{
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

        if ($item->boss_id) {
            Cache::forget("loot_items.boss_{$item->boss_id}");
        } else {
            Cache::forget("loot_items.trash_raid_{$item->raid_id}");
        }

        return redirect()->back();
    }
}
