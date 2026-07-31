<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\LootCouncillorCollection;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\GuildTag;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

#[Authorize('view-officer-dashboard')]
class AddonSettingsController extends Controller
{
    /**
     * Render the addon settings page.
     */
    public function __invoke(Request $request)
    {
        $councillors = Character::where('is_loot_councillor', true)
            ->with(['rank', 'media'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Manage/Addon/Settings', [
            'councillors' => new LootCouncillorCollection($councillors),
            'ranks' => GuildRank::orderBy('position')->get()->toResourceCollection(),
            'tags' => GuildTag::with('phase')->orderBy('name')->get()->toResourceCollection(),
            'characters' => Inertia::defer(function () {
                return Character::where('is_main', true)->with('rank')->orderBy('name')->get();
            }),
        ]);
    }
}
