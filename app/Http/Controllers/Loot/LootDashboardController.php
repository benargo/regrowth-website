<?php

namespace App\Http\Controllers\Loot;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loot\UpdateItemPrioritiesRequest;
use App\Http\Resources\LootCouncil\ItemResource;
use App\Http\Resources\LootCouncil\PriorityResource;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LootDashboardController extends Controller
{
    public function index(Request $request)
    {
        $phases = Phase::all();
        $currentPhase = $phases->where('start_date', '<=', now())->sortByDesc('start_date')->first();

        $raids = Raid::all();
        $groupedRaids = $raids->groupBy('phase_id');
        $bosses = Boss::orderBy('encounter_order')->get()->groupBy('raid_id');

        // Determine which raid to load items for
        $defaultRaidId = $groupedRaids[$currentPhase->id][0]->id ?? null;
        $selectedRaidId = $request->input('raid_id', $defaultRaidId);
        $selectedPhaseId = $raids->find($selectedRaidId)->phase_id ?? $currentPhase->id;

        return Inertia::render('Loot/Index', [
            'phases' => $phases,
            'current_phase' => $selectedPhaseId,
            'raids' => $groupedRaids,
            'bosses' => $bosses,
            'selected_raid_id' => (int) $selectedRaidId,
            'items' => Inertia::defer(fn () => $this->getItemsForRaid($selectedRaidId)),
        ]);
    }

    public function showItem(Item $item, Request $request)
    {
        $item->load([
            'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
        ]);

        return Inertia::render('Loot/ItemShow', [
            'item' => new ItemResource($item)->withRaid()->withBoss(),
            'can_edit' => $request->user()->can('edit-loot-priorities'),
        ]);
    }

    public function editItem(Item $item)
    {
        $item->load([
            'priorities' => fn ($q) => $q->orderByPivot('weight', 'asc'),
        ]);

        $allPriorities = Priority::all();

        return Inertia::render('Loot/ItemEdit', [
            'item' => new ItemResource($item)->withRaid()->withBoss(),
            'allPriorities' => PriorityResource::collection($allPriorities),
        ]);
    }

    public function updateItemPriorities(UpdateItemPrioritiesRequest $request, Item $item): RedirectResponse
    {
        $priorities = collect($request->validated('priorities'))
            ->mapWithKeys(fn ($p) => [$p['priority_id'] => ['weight' => $p['weight']]])
            ->all();

        $item->priorities()->sync($priorities);

        return redirect()->route('loot.items.show', $item);
    }

    /**
     * Get items for a specific raid, grouped by boss_id.
     *
     * @return array<int|string, array<int, mixed>>
     */
    protected function getItemsForRaid(?int $raidId)
    {
        if (! $raidId) {
            return [];
        }

        $items = Item::query()
            ->where('raid_id', $raidId)
            ->with([
                'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
            ])
            ->get();

        return ItemResource::collection($items)
            ->collection
            ->groupBy(fn ($item) => $item['boss_id'] ?? -1)
            ->toArray();
    }
}
