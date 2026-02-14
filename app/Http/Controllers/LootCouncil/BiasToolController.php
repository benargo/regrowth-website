<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Http\Resources\LootCouncil\BossItemsResource;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
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

        return redirect()->route('loot.phase', ['phase' => $currentPhase->id]);
    }

    public function phase(Phase $phase, Request $request)
    {
        // Reload phases to ensure we have the latest data for the current phase (in case it was just switched)
        $phases = Cache::remember('phases.tbc.index', now()->addYear(), fn () => Phase::all());

        // Preload raids and bosses for the current phase to minimize latency when switching between them
        $raids = Cache::remember('raids.tbc.index', now()->addYear(), fn () => Raid::all());
        $groupedRaids = $raids->groupBy('phase_id');

        // Determine which raid to load items for
        $defaultRaidId = $groupedRaids[$phase->id][0]->id ?? null;
        $selectedRaidId = $request->input('raid_id', $defaultRaidId);
        $selectedPhaseId = $raids->find($selectedRaidId)->phase_id ?? $phase->id;

        return Inertia::render('LootBiasTool/Phase', [
            'current_phase' => $selectedPhaseId,
            'raids' => $groupedRaids,
            'selected_raid_id' => (int) $selectedRaidId,
            'bosses' => Inertia::defer(fn () => $this->getBossesForRaid($selectedRaidId)),
            // Only load boss items when explicitly requested via partial reload
            'boss_items' => Inertia::optional(fn () => $this->getItemsForBoss(
                $request->integer('boss_id')
            )),
        ]);
    }

    protected function getBossesForRaid(int $raidId): array
    {
        return Cache::tags(['lootcouncil'])->remember(
            "bosses.tbc.raid_{$raidId}.index",
            now()->addMonth(),
            function () use ($raidId) {
                $bosses = Boss::where('raid_id', $raidId)
                    ->orderBy('encounter_order')
                    ->get();

                // Get comment counts per boss via a single query
                $commentsCount = Comment::query()
                    ->join('lootcouncil_items', 'lootcouncil_items.id', '=', 'lootcouncil_comments.item_id')
                    ->whereNotNull('lootcouncil_items.boss_id')
                    ->selectRaw('lootcouncil_items.boss_id, count(*) as comments_count')
                    ->groupBy('lootcouncil_items.boss_id')
                    ->pluck('comments_count', 'boss_id');

                // Add comment counts to each boss
                $bosses->each(fn ($boss) => $boss->comments_count = $commentsCount->get($boss->id, 0));

                // Add virtual "Trash drops" boss for raids that have items without a boss
                $hasTrashDrops = Item::query()
                    ->where([
                        ['raid_id', $raidId],
                        ['boss_id', null],
                    ])
                    ->count();

                if ($hasTrashDrops > 0) {
                    // Get comment counts per boss via a single query
                    $commentsCount = Comment::query()
                        ->join('lootcouncil_items', 'lootcouncil_items.id', '=', 'lootcouncil_comments.item_id')
                        ->whereNull('lootcouncil_items.boss_id')
                        ->selectRaw('lootcouncil_items.boss_id, count(*) as comments_count')
                        ->groupBy('lootcouncil_items.boss_id')
                        ->pluck('comments_count', 'boss_id');

                    $bosses->push([
                        'id' => -1 * $raidId,
                        'raid_id' => $raidId,
                        'name' => 'Trash drops',
                        'encounter_order' => 999,
                        'comments_count' => $commentsCount->get(null, 0),
                    ]);
                }

                return $bosses->groupBy('raid_id')->toArray();
            }
        );
    }

    /**
     * Get items for a specific boss.
     */
    protected function getItemsForBoss(?int $bossId): BossItemsResource
    {
        if (! $bossId) {
            return new BossItemsResource([
                'bossId' => null,
                'items' => [],
                'comments_count' => 0,
            ]);
        }

        if ($bossId < 0) {
            // Trash boss IDs are negative raid IDs (-1 * raidId)
            $raidId = abs($bossId);

            return $this->getTrashItemsForRaid($raidId);
        }

        $items = Cache::tags(['lootcouncil'])->remember(
            "loot_items.boss_{$bossId}.index",
            now()->addWeek(),
            fn () => Item::query()
                ->where('boss_id', $bossId)
                ->with([
                    'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
                ])
                ->withCount('comments')
                ->get()
        );

        return new BossItemsResource([
            'bossId' => $bossId,
            'items' => $items,
            'commentsCount' => $items->sum('comments_count'),
        ]);
    }

    /**
     * Get trash items for a specific raid.
     */
    protected function getTrashItemsForRaid(?int $raidId = null): BossItemsResource
    {
        if (! $raidId) {
            $raidId = 1;
        }

        $items = Cache::tags(['lootcouncil'])->remember(
            "loot_items.trash_raid_{$raidId}.index",
            now()->addWeek(),
            fn () => Item::query()
                ->where('raid_id', $raidId)
                ->whereNull('boss_id')
                ->with([
                    'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
                ])
                ->withCount('comments')
                ->get()
        );

        return new BossItemsResource([
            'bossId' => -1 * $raidId,
            'items' => $items,
            'commentsCount' => $items->sum('comments_count'),
        ]);
    }
}
