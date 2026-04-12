<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Http\Resources\LootCouncil\BossItemsResource;
use App\Models\LootCouncil\Item;
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
        $phases = Phase::hydrate(
            Cache::remember('phases:tbc:index', now()->addYear(), fn () => Phase::all()->toArray())
        );

        $currentPhase = $phases->where('start_date', '<=', now())->sortByDesc('start_date')->first();

        return redirect()->route('loot.phase', ['phase' => $currentPhase->id]);
    }

    public function phase(Phase $phase, Request $request)
    {
        // Preload phases to ensure we have the latest data for the current phase (in case it was just switched)
        $phases = Phase::hydrate(
            Cache::remember('phases:tbc:index', now()->addYear(), fn () => Phase::all()->toArray())
        );

        // Preload raids and bosses for the current phase to minimize latency when switching between them
        $raids = Raid::hydrate(
            Cache::remember('raids:tbc:index', now()->addYear(), fn () => Raid::all()->toArray())
        );
        $groupedRaids = $raids->groupBy('phase_id');

        // Determine which raid to load items for
        $defaultRaidId = $groupedRaids[$phase->id][0]->id ?? null;
        $selectedRaid = $raids->find($request->input('raid_id', $defaultRaidId));

        return Inertia::render('LootBiasTool/Phase', [
            'current_phase' => $phase->id,
            'raids' => $groupedRaids,
            'selected_raid_id' => (int) $selectedRaid->id,
            'bosses' => Inertia::defer(fn () => $this->getBossesForRaid($selectedRaid)),
            // Only load boss items when explicitly requested via partial reload
            'boss_items' => Inertia::optional(fn () => $this->getItemsForBoss(
                $request->integer('boss_id')
            )),
        ]);
    }

    protected function getBossesForRaid(Raid $raid): array
    {
        return Cache::tags(['lootcouncil'])->remember(
            "bosses:tbc:raid_{$raid->id}:index",
            now()->addMonth(),
            function () use ($raid) {
                $bosses = $raid->bosses()
                    ->orderBy('encounter_order')
                    ->withCount('comments')
                    ->get();

                if ($raid->trashItems()->exists()) {
                    $bosses->push([
                        'id' => -1 * $raid->id,
                        'raid_id' => $raid->id,
                        'name' => 'Trash drops',
                        'encounter_order' => 999,
                        'comments_count' => $raid->comments()->whereNull('lootcouncil_items.boss_id')->count(),
                    ]);
                }

                return $bosses->groupBy('raid_id')->toArray();
            }
        );
    }

    /**
     * Get items for a specific boss.
     */
    protected function getItemsForBoss(?int $bossId): array
    {
        if (! $bossId) {
            return (new BossItemsResource([
                'bossId' => null,
                'items' => collect(),
                'comments_count' => 0,
            ]))->response(request())->getData(true);
        }

        if ($bossId < 0) {
            // Trash boss IDs are negative raid IDs (-1 * raidId)
            $raidId = abs($bossId);

            return $this->getTrashItemsForRaid($raidId);
        }

        return Cache::tags(['lootcouncil'])->remember(
            "loot_items:boss_{$bossId}:index",
            now()->addWeek(),
            function () use ($bossId) {
                $items = Item::query()
                    ->where('boss_id', $bossId)
                    ->with([
                        'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
                    ])
                    ->withCount('comments')
                    ->get();

                return (new BossItemsResource([
                    'bossId' => $bossId,
                    'items' => $items,
                    'commentsCount' => $items->sum('comments_count'),
                ]))->response(request())->getData(true);
            }
        );
    }

    /**
     * Get trash items for a specific raid.
     */
    protected function getTrashItemsForRaid(?int $raidId = null): array
    {
        if (! $raidId) {
            $raidId = 1;
        }

        return Cache::tags(['lootcouncil'])->remember(
            "loot_items:trash_raid_{$raidId}:index",
            now()->addWeek(),
            function () use ($raidId) {
                $items = Item::query()
                    ->where('raid_id', $raidId)
                    ->whereNull('boss_id')
                    ->with([
                        'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
                    ])
                    ->withCount('comments')
                    ->get();

                return (new BossItemsResource([
                    'bossId' => -1 * $raidId,
                    'items' => $items,
                    'commentsCount' => $items->sum('comments_count'),
                ]))->response(request())->getData(true);
            }
        );
    }
}
