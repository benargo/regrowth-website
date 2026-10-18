<?php

namespace App\Actions\Items;

use App\Enums\LootPriorityType;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * List the phases, in release order, that have an item with a non-Meme loot priority.
 */
class ListPhasesWithTierOnePriorities
{
    use AsAction;

    /**
     * @return Collection<int, Phase>
     */
    public function handle(): Collection
    {
        return Phase::whereExists(fn ($query) => $query->from('raids')
            ->join('pivot_items_raids as pir', 'pir.raid_id', '=', 'raids.id')
            ->join('pivot_items_priorities as pip', 'pip.item_id', '=', 'pir.item_id')
            ->join('loot_priorities as lp', 'lp.id', '=', 'pip.priority_id')
            ->where('lp.type', '!=', LootPriorityType::MEME->value)
            ->whereColumn('raids.phase_id', 'phases.id'))
            ->inReleaseOrder()
            ->get();
    }
}
