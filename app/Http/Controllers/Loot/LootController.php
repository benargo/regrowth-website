<?php

namespace App\Http\Controllers\Loot;

use App\Http\Controllers\Concerns\QueriesLootCouncilCache;
use App\Http\Controllers\Controller;
use App\Http\Resources\PhaseResource;
use App\Models\Phase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LootController extends Controller
{
    use QueriesLootCouncilCache;

    /**
     * Redirect to the first raid of the current phase.
     */
    public function index(Request $request): Response
    {
        $phases = PhaseResource::collection(Phase::with('raids')->orderBy('number')->get())->resolve($request);

        return Inertia::render('Loot/Index', [
            'phases' => $phases,
        ]);
    }
}
