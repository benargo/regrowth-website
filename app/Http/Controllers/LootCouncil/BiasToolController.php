<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BiasToolController extends Controller
{
    /**
     * Redirect to the first raid of the current phase.
     */
    public function index(Request $request)
    {
        $currentPhase = Phase::where('start_date', '<=', now())->orderBy('start_date', 'desc')->first();

        $firstRaid = $this->getRaidsForPhase($currentPhase)->first();

        return redirect()->route('loot.raids.show', ['raid' => $firstRaid->id, 'name' => Str::slug($firstRaid->name)]);
    }

    /**
     * Redirect to the last visited raid of the specified phase, falling back to the first raid.
     */
    public function phase(Phase $phase, Request $request)
    {
        $raids = $this->getRaidsForPhase($phase);

        $lastVisitedRaidId = $request->session()->get("loot.last_visited_raid.{$phase->id}");
        $raid = $lastVisitedRaidId ? $raids->firstWhere('id', $lastVisitedRaidId) : null;
        $raid ??= $raids->first();

        return redirect()->route('loot.raids.show', ['raid' => $raid->id, 'name' => Str::slug($raid->name)]);
    }

    /**
     * Get raids for a specific phase, with caching.
     *
     * @return EloquentCollection<Raid>
     */
    private function getRaidsForPhase(Phase $phase): EloquentCollection
    {
        return Raid::hydrate(
            Cache::tags(['db', 'lootcouncil'])->remember("phases:#{$phase->id}:raids", now()->addYear(), function () use ($phase) {
                return $phase->raids()->get()->toArray();
            })
        );
    }
}
