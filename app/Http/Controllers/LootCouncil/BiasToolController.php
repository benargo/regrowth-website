<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Http\Resources\LootCouncil\ItemResource;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class BiasToolController extends Controller
{
    /**
     * Display the loot priority manager dashboard.
     */
    public function index(Request $request)
    {
        $phases = Cache::remember('phases.tbc.index', now()->addYear(), fn () => Phase::all());

        $currentPhase = $phases->where('start_date', '<=', now())->sortByDesc('start_date')->first();

        if ($currentPhase === null) {
            $currentPhase = $phases->first();
        }

        // Preload raids and bosses for the current phase to minimize latency when switching between them
        $raids = Cache::remember('raids.tbc.index', now()->addYear(), fn () => Raid::all());
        $groupedRaids = $raids->groupBy('phase_id');
        $bosses = Cache::remember('bosses.tbc.index', now()->addYear(), fn () => Boss::orderBy('encounter_order')->get()->groupBy('raid_id'));

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
            // Only load boss items when explicitly requested via partial reload
            'boss_items' => Inertia::optional(fn () => $this->getItemsForBoss(
                $request->integer('boss_id')
            )),
        ]);
    }

    /**
     * Get items for a specific boss.
     *
     * @return array{boss_id: int, items: array<int, mixed>}
     */
    protected function getItemsForBoss(?int $bossId): array
    {
        if (! $bossId) {
            return [];
        }

        if ($bossId === -1) {
            return $this->getTrashItemsForRaid(request()->input('raid_id'));
        }

        $items = Cache::remember(
            "loot_items.boss_{$bossId}",
            now()->addDays(7),
            fn () => Item::query()
                ->where('boss_id', $bossId)
                ->with([
                    'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
                ])
                ->get()
        );

        return [
            'boss_id' => $bossId,
            'items' => ItemResource::collection($items)->collection->toArray(),
        ];
    }

    /**
     * Get trash items for a specific raid.
     *
     * @return array{boss_id: int, items: array<int, mixed>}
     */
    protected function getTrashItemsForRaid(?int $raidId = null): array
    {
        if (! $raidId) {
            $raidId = 1;
        }

        $items = Cache::remember(
            "loot_items.trash_raid_{$raidId}",
            now()->addDays(7),
            fn () => Item::query()
                ->where('raid_id', $raidId)
                ->where('boss_id', null)
                ->with([
                    'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
                ])
                ->get()
        );

        return [
            'boss_id' => -1,
            'items' => ItemResource::collection($items)->collection->toArray(),
        ];
    }
}
