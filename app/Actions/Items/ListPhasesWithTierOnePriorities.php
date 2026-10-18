<?php

namespace App\Actions\Items;

use App\Models\Phase;
use Illuminate\Database\Eloquent\Builder;
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
        return Phase::whereHas('raids.items.priorities', fn (Builder $query) => $query->withoutMemes())
            ->inReleaseOrder()
            ->get();
    }
}
