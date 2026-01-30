<?php

namespace App\Http\Controllers\Loot;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loot\UpdateItemPrioritiesRequest;
use App\Http\Resources\LootCouncil\ItemCommentResource;
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

class LootController extends Controller
{
    /**
     * Display the loot priority manager dashboard.
     */
    public function index(Request $request)
    {
        $phases = Phase::all();
        $currentPhase = $phases->where('start_date', '<=', now())->sortByDesc('start_date')->first();

        if ($currentPhase === null) {
            $currentPhase = $phases->first();
        }

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

    /**
     * Display a specific loot item.
     */
    public function showItem(Item $item, Request $request)
    {
        $item->load([
            'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
            'raid',
            'boss',
        ]);

        $comments = $item->comments()
            ->with('user')
            ->latest()
            ->paginate(10);

        return Inertia::render('Loot/ItemShow', [
            'item' => new ItemResource($item),
            'can' => [
                'create_comment' => $request->user()->can('create-loot-comment'),
                'edit_item' => $request->user()->can('edit-loot-items'),
            ],
            'comments' => ItemCommentResource::collection($comments),
        ]);
    }

    /**
     * Show the form for editing a specific loot item.
     */
    public function editItem(Item $item, Request $request)
    {
        $item->load([
            'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
            'raid',
            'boss',
        ]);

        $comments = $item->comments()
            ->with('user')
            ->latest()
            ->paginate(10);

        $allPriorities = Priority::all();

        return Inertia::render('Loot/ItemEdit', [
            'item' => new ItemResource($item),
            'allPriorities' => PriorityResource::collection($allPriorities),
            'can' => [
                'create_comment' => $request->user()->can('create-loot-comment'),
                'edit_item' => $request->user()->can('edit-loot-items'),
            ],
            'comments' => ItemCommentResource::collection($comments),
        ]);
    }

    /**
     * Update the priorities for a specific loot item.
     */
    public function updateItemPriorities(UpdateItemPrioritiesRequest $request, Item $item): RedirectResponse
    {
        $priorities = collect($request->validated('priorities'))
            ->mapWithKeys(fn ($p) => [$p['priority_id'] => ['weight' => $p['weight']]])
            ->all();

        $item->priorities()->sync($priorities);

        return redirect()->back();
    }

    /**
     * Update the officers' notes for a specific loot item.
     */
    public function updateItemNotes(Request $request, Item $item): RedirectResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:5000',
        ]);

        $item->notes = $request->input('notes');
        $item->save();

        return redirect()->back();
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
