<?php

namespace App\Actions\Items;

use App\Data\Items\ClassStatsGroupData;
use App\Data\Items\HighestPriorityStatsTableData;
use App\Data\Items\PriorityStatsRowData;
use App\Enums\LootPriorityType;
use App\Models\ItemPriority;
use App\Models\LootPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Build the highest-priority stats table of role rows and playable class groups.
 */
class BuildHighestPriorityStatsTable
{
    use AsAction;

    /**
     * @param  Collection<int, int>  $phaseIds
     */
    public function handle(Collection $phaseIds): HighestPriorityStatsTableData
    {
        if ($phaseIds->isEmpty()) {
            return new HighestPriorityStatsTableData(roleRows: collect(), classGroups: collect());
        }

        $priorities = LootPriority::withoutMemes()
            ->with(['media', 'playableClass.media'])
            ->orderBy('id')
            ->get();

        $emptyCounts = array_fill_keys($phaseIds->all(), 0);
        $countsByPriority = $this->tierOneCounts($phaseIds);

        $priorityRow = fn (LootPriority $priority) => PriorityStatsRowData::fromPriority(
            $priority,
            array_replace($emptyCounts, $countsByPriority->get($priority->id, [])),
        );

        $roleRows = $priorities
            ->where('type', LootPriorityType::ROLE)
            ->map($priorityRow)
            ->values();

        $classGroups = $priorities
            ->whereNotNull('playable_class_id')
            ->sortBy([
                fn (LootPriority $a, LootPriority $b) => $a->playableClass->name <=> $b->playableClass->name,
                fn (LootPriority $a, LootPriority $b) => $a->type->sortOrder() <=> $b->type->sortOrder(),
                fn (LootPriority $a, LootPriority $b) => $a->title <=> $b->title,
            ])
            ->groupBy('playable_class_id')
            ->map(fn (Collection $group) => ClassStatsGroupData::fromPlayableClass(
                $group->first()->playableClass,
                $group->map($priorityRow),
            ))
            ->values();

        return new HighestPriorityStatsTableData(roleRows: $roleRows, classGroups: $classGroups);
    }

    /**
     * Get the number of tier-1 items per phase for each loot priority, keyed by priority id then phase id.
     *
     * @param  Collection<int, int>  $phaseIds
     * @return Collection<int, array<int, int>>
     */
    private function tierOneCounts(Collection $phaseIds): Collection
    {
        $excludeMemes = fn (Builder $query) => $query->withoutMemes();

        $tierOneWeights = ItemPriority::whereHas('priority', $excludeMemes)
            ->groupBy('item_id')
            ->select('item_id')
            ->selectRaw('MIN(weight) as min_weight');

        return ItemPriority::joinSub($tierOneWeights, 'tier1', function (JoinClause $join) {
            $join->on('tier1.item_id', '=', 'pivot_items_priorities.item_id')
                ->on('tier1.min_weight', '=', 'pivot_items_priorities.weight');
        })
            ->join('pivot_items_raids', 'pivot_items_raids.item_id', '=', 'pivot_items_priorities.item_id')
            ->join('raids', 'raids.id', '=', 'pivot_items_raids.raid_id')
            ->whereHas('priority', $excludeMemes)
            ->whereIn('raids.phase_id', $phaseIds)
            ->groupBy('pivot_items_priorities.priority_id', 'raids.phase_id')
            ->select('pivot_items_priorities.priority_id', 'raids.phase_id')
            ->selectRaw('COUNT(DISTINCT pivot_items_priorities.item_id) as item_count')
            ->toBase()
            ->get()
            ->groupBy('priority_id')
            ->map(fn (Collection $rows) => $rows->mapWithKeys(fn (object $row) => [(int) $row->phase_id => (int) $row->item_count])->all());
    }
}
