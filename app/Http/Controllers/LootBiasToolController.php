<?php

namespace App\Http\Controllers;

use App\Http\Resources\RaidResource;
use App\Models\Raid;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LootBiasToolController extends Controller
{
    public function index(Request $request): Response
    {
        $raids = RaidResource::collection(
            Raid::with('phase')->orderBy('phase_id')->orderBy('id')->get()
        )->resolve($request);

        return Inertia::render('Loot/Index', [
            'raids' => $raids,
        ]);
    }
}
