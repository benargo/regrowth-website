<?php

namespace App\Http\Controllers\Loot;

use App\Http\Controllers\Concerns\QueriesLootCouncilCache;
use App\Http\Controllers\Controller;
use App\Http\Resources\RaidResource;
use App\Models\Raid;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LootController extends Controller
{
    use QueriesLootCouncilCache;

    public function index(Request $request): Response
    {
        $raids = RaidResource::collection(Raid::with('phase')->orderBy('phase_id')->orderBy('id')->get())->resolve($request);

        return Inertia::render('Loot/Index', [
            'raids' => $raids,
        ]);
    }
}
