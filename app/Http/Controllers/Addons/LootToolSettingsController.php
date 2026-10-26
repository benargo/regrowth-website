<?php

namespace App\Http\Controllers\Addons;

use App\Actions\Addon\UpdateAddonSettings;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAddonSettingsRequest;
use App\Http\Resources\CharacterSummaryResource;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\GuildRankResource;
use App\Http\Resources\LootCouncillorCollection;
use App\Http\Resources\WarcraftLogs\GuildTagResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class LootToolSettingsController extends Controller
{
    /**
     * Render the addon settings page for the current game version.
     */
    public function show(): Response
    {
        $gameVersion = ResolveCurrentGameVersion::run();

        $councillors = $gameVersion?->characters()->where('is_loot_councillor', true)->with(['rank', 'media'])->orderBy('name')->get();
        $ranks = $gameVersion?->guildRanks()->ordered()->get();
        $tags = $gameVersion?->warcraftLogsGuild?->guildTags()->orderBy('name')->get();

        return Inertia::render('Addons/LootTool/Settings', [
            'gameVersion' => $gameVersion === null ? null : new GameVersionResource($gameVersion),
            'councillors' => new LootCouncillorCollection($councillors ?? collect()),
            'ranks' => GuildRankResource::collection($ranks ?? collect()),
            'tags' => GuildTagResource::collection($tags ?? collect()),
            'characters' => Inertia::defer(fn () => CharacterSummaryResource::collection(
                $gameVersion?->characters()->where('is_main', true)->with('rank')->orderBy('name')->get() ?? collect()
            )),
        ]);
    }

    /**
     * Save the game version's addon settings.
     */
    public function update(UpdateAddonSettingsRequest $request): RedirectResponse
    {
        UpdateAddonSettings::run(
            $request->gameVersion(),
            $request->safe()->only(['councillor_ids', 'counted_rank_ids', 'counted_tag_ids']),
        );

        return back()->with('success', 'Addon settings saved.');
    }
}
