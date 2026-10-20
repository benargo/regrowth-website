<?php

namespace App\Actions\Items;

use App\Models\GameVersion;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * List the given game version's phases (or the phases with no version when it is null), in release order, that have an item with a non-Meme loot priority.
 */
class ListPhasesWithTierOnePriorities
{
    use AsAction;

    /**
     * @return Collection<int, Phase>
     */
    public function handle(?GameVersion $gameVersion): Collection
    {
        return Phase::where('game_version_id', $gameVersion?->id)
            ->whereHas('raids.items.priorities', fn (Builder $query) => $query->withoutMemes())
            ->inReleaseOrder()
            ->get();
    }
}
