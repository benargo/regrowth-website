<?php

namespace App\Http\Controllers;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Actions\Items\BuildHighestPriorityStatsTable;
use App\Actions\Items\BuildLootStats;
use App\Actions\Items\ListPhasesWithTierOnePriorities;
use App\Data\Items\HighestPriorityStatsTableData;
use App\Http\Resources\PhaseResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class LootBiasToolController extends Controller
{
    /**
     * Display the loot hub with the current game version's loot stats.
     */
    public function index(): Response
    {
        $gameVersion = ResolveCurrentGameVersion::run();

        return Inertia::render('Loot/Index', [
            'stats' => BuildLootStats::run($gameVersion),
        ]);
    }

    #[Authorize('view-priorities-page')]
    #[Middleware('auth')]
    public function priorities(): Response
    {
        $gameVersion = ResolveCurrentGameVersion::run();
        $phases = $gameVersion === null ? new Collection : ListPhasesWithTierOnePriorities::run($gameVersion);

        return Inertia::render('Loot/Priorities', [
            'phases' => PhaseResource::collection($phases),
            'table' => $phases->isEmpty() ? null : Inertia::defer(fn () => HighestPriorityStatsTableData::from(Cache::tags(['lootcouncil'])->remember(
                "loot:priorities:highest-priority-stats:{$gameVersion->id}",
                now()->addHours(24),
                fn (): array => BuildHighestPriorityStatsTable::run($phases->pluck('id'))->toArray(),
            ))),
        ]);
    }
}
