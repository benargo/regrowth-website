<?php

namespace App\Http\Controllers;

use App\Http\Resources\PhaseResource;
use App\Http\Resources\RaidBossesCollection;
use App\Models\Boss;
use App\Models\Phase;
use Inertia\Inertia;
use Inertia\Response;

class BossStrategyController extends Controller
{
    /**
     * Show every raid's bosses, by phase, linking to each boss's strategy.
     */
    public function index(): Response
    {
        return Inertia::render('Raiding/BossStrategies/Index', [
            'bosses' => new RaidBossesCollection(Boss::orderBy('raid_id')->ordered()->get()),
            'phases' => PhaseResource::collection(Phase::with('raids')->inReleaseOrder()->get()),
        ]);
    }

    /**
     * Show a boss's strategy.
     */
    public function show(Boss $boss, string $slug): Response
    {
        return Inertia::render('Raiding/BossStrategies/Show', [
            'boss' => $boss->load('raid.media')->toResource(),
        ]);
    }
}
