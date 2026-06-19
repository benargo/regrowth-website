<?php

namespace App\Http\Controllers;

use App\Http\Resources\RaidResource;
use App\Models\Raid;
use Inertia\Inertia;
use Inertia\Response;

class LootBiasToolController extends Controller
{
    public function index(): Response
    {
        $raids = RaidResource::collection(
            Raid::with('phase')->orderBy('phase_id')->orderBy('id')->get()
        );

        return Inertia::render('Loot/Index', [
            'raids' => $raids,
        ]);
    }
}
