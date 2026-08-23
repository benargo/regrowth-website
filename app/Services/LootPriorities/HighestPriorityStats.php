<?php

namespace App\Services\LootPriorities;

use App\Enums\LootPriorityType;
use App\Models\LootPriority;
use App\Models\Phase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HighestPriorityStats
{
    /**
     * Get the phases that have at least one item with a non-Meme tier-1 priority.
     *
     * @return Collection<int, Phase>
     */
    public function phases(): Collection
    {
        return Phase::query()
            ->whereExists(fn ($query) => $query->from('raids')
                ->join('pivot_items_raids as pir', 'pir.raid_id', '=', 'raids.id')
                ->join('pivot_items_priorities as pip', 'pip.item_id', '=', 'pir.item_id')
                ->join('loot_priorities as lp', 'lp.id', '=', 'pip.priority_id')
                ->where('lp.type', '!=', LootPriorityType::MEME->value)
                ->whereColumn('raids.phase_id', 'phases.id'))
            ->orderBy('number')
            ->get();
    }

    /**
     * Build the priority rows for the stats table, each carrying a tier-1 count per phase.
     *
     * @param  array<int, int>  $phaseIds
     * @return array<int, array<string, mixed>>
     */
    public function table(array $phaseIds): array
    {
        if ($phaseIds === []) {
            return [];
        }

        $counts = $this->tierOneCounts();

        $priorities = LootPriority::query()
            ->where('type', '!=', LootPriorityType::MEME)
            ->with(['media', 'playableClass.media'])
            ->get();

        $emptyCounts = array_fill_keys($phaseIds, 0);

        $priorityRow = function (LootPriority $priority) use ($counts, $emptyCounts): array {
            $priorityCounts = $counts->get($priority->id, collect())
                ->mapWithKeys(fn ($count, $phaseId) => [$phaseId => $count])
                ->all();
            $rowCounts = array_replace($emptyCounts, $priorityCounts);

            return [
                'kind' => 'priority',
                'id' => $priority->id,
                'title' => $priority->title,
                'type' => $priority->type->value,
                'icon' => $priority->getFirstMediaUrl('blizzard_icons') ?: null,
                'counts' => $rowCounts,
                'total' => array_sum($rowCounts),
            ];
        };

        $roleRows = $priorities
            ->where('type', LootPriorityType::ROLE)
            ->sortBy('id')
            ->map($priorityRow)
            ->values();

        $classGroups = $priorities
            ->whereNotNull('playable_class_id')
            ->groupBy('playable_class_id')
            ->sortBy(fn (Collection $group) => $group->first()->playableClass->name)
            ->map(function (Collection $group) use ($priorityRow, $emptyCounts) {
                $playableClass = $group->first()->playableClass;

                $children = $group
                    ->sortBy([
                        fn (LootPriority $a, LootPriority $b) => $a->type->sortOrder() <=> $b->type->sortOrder(),
                        fn (LootPriority $a, LootPriority $b) => $a->title <=> $b->title,
                    ])
                    ->map($priorityRow)
                    ->values();

                $groupCounts = $emptyCounts;
                foreach ($children as $child) {
                    foreach ($child['counts'] as $phaseId => $count) {
                        $groupCounts[$phaseId] += $count;
                    }
                }

                return [
                    'kind' => 'class',
                    'id' => $playableClass->id,
                    'title' => $playableClass->name,
                    'slug' => $playableClass->slug,
                    'icon' => $playableClass->getFirstMediaUrl('blizzard_icons') ?: null,
                    'counts' => $groupCounts,
                    'total' => array_sum($groupCounts),
                    'children' => $children->all(),
                ];
            })
            ->values();

        return $roleRows->concat($classGroups)->all();
    }

    /**
     * Get the tier-1 item count for each priority, keyed by priority id then phase id.
     *
     * A tier-1 item is one where the priority holds the lowest weight among that
     * item's non-Meme priorities; ties all count. COUNT(DISTINCT pip.item_id) is
     * required because an item can belong to multiple raids that share one phase
     * (pivot_items_raids fans out one row per raid), which would otherwise inflate
     * the count for that phase.
     *
     * @return Collection<int, Collection<int, int>>
     */
    protected function tierOneCounts(): Collection
    {
        $tierOneWeights = DB::table('pivot_items_priorities')
            ->join('loot_priorities as lpm', 'lpm.id', '=', 'pivot_items_priorities.priority_id')
            ->where('lpm.type', '!=', LootPriorityType::MEME->value)
            ->groupBy('pivot_items_priorities.item_id')
            ->select('pivot_items_priorities.item_id', DB::raw('MIN(pivot_items_priorities.weight) as min_weight'));

        $rows = DB::table('pivot_items_priorities as pip')
            ->joinSub($tierOneWeights, 'tier1', function ($join) {
                $join->on('tier1.item_id', '=', 'pip.item_id')
                    ->on('tier1.min_weight', '=', 'pip.weight');
            })
            ->join('loot_priorities as lp', 'lp.id', '=', 'pip.priority_id')
            ->join('pivot_items_raids as pir', 'pir.item_id', '=', 'pip.item_id')
            ->join('raids as r', 'r.id', '=', 'pir.raid_id')
            ->where('lp.type', '!=', LootPriorityType::MEME->value)
            ->whereNotNull('r.phase_id')
            ->groupBy('pip.priority_id', 'r.phase_id')
            ->select('pip.priority_id', 'r.phase_id', DB::raw('COUNT(DISTINCT pip.item_id) as tier_one_count'))
            ->get();

        return $rows
            ->groupBy('priority_id')
            ->map(fn (Collection $group) => $group->pluck('tier_one_count', 'phase_id'));
    }
}
