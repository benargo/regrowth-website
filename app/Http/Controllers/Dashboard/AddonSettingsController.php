<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AddCouncillorRequest;
use App\Http\Requests\Dashboard\RemoveCouncillorRequest;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\GuildTag;
use App\Models\Phase;
use App\Services\WarcraftLogs\GuildTags;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AddonSettingsController extends Controller
{
    public function __construct(
        protected GuildTags $guildTags,
    ) {}

    /**
     * Render the addon settings page.
     */
    public function index(Request $request)
    {
        $councillors = Character::where('is_loot_councillor', true)
            ->orderBy('name')
            ->get();

        $tags = $this->guildTags
            ->toCollection()
            ->map(function (GuildTag $tag) {
                $phase = null;
                if ($tag->phase instanceof Phase) {
                    $phase = $tag->phase->number;
                }

                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'count_attendance' => $tag->count_attendance,
                    'phaseNumber' => $phase,
                ];
            })
            ->values()
            ->toArray();

        return Inertia::render('Dashboard/Addon/Settings', [
            'settings' => [
                'councillors' => $councillors,
                'ranks' => GuildRank::orderBy('position')->get(),
                'tags' => $tags,
            ],
            'characters' => Inertia::defer(fn () => Character::with('rank')->orderBy('name')->get()),
        ]);
    }

    /**
     * Add a councillor to the list of loot councillors.
     */
    public function addCouncillor(AddCouncillorRequest $request): RedirectResponse
    {
        $character = Character::where('name', $request->validated('character_name'))->firstOrFail();

        $character->update(['is_loot_councillor' => true]);

        return back();
    }

    /**
     * Remove a councillor from the list of loot councillors.
     */
    public function removeCouncillor(RemoveCouncillorRequest $request, Character $character): RedirectResponse
    {
        $character->update(['is_loot_councillor' => false]);

        return back();
    }
}
