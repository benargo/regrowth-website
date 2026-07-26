<?php

namespace App\Http\Controllers;

use App\Http\Resources\PhaseResource;
use App\Http\Resources\RaidBossesCollection;
use App\Models\Boss;
use App\Models\Phase;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BossStrategiesController extends Controller
{
    /**
     * Show a list of all bosses to navigate to individual strategy pages.
     */
    public function index(Request $request)
    {
        $phases = Phase::with(['raids'])->orderBy('number')->get();

        return Inertia::render('Raiding/BossStrategies/Index', [
            'bosses' => new RaidBossesCollection(Boss::orderBy('raid_id')->orderBy('encounter_order')->get()),
            'phases' => PhaseResource::collection($phases)->resolve($request),
        ]);
    }

    /**
     * Show a boss's strategy.
     */
    public function show(Boss $boss, string $slug)
    {
        return Inertia::render('Raiding/BossStrategies/Show', [
            'boss' => $boss->load('raid')->toResource(),
        ]);
    }
}
