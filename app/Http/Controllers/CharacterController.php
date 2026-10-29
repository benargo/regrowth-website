<?php

namespace App\Http\Controllers;

use App\Actions\Characters\RefreshCharacterPortrait;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Requests\UpdateCharacterRequest;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\GuildRosterMemberCollection;
use App\Http\Resources\PlayableClassResource;
use App\Http\Resources\PlayableRaceResource;
use App\Http\Resources\PlayableSpecializationResource;
use App\Models\Character;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class CharacterController extends Controller
{
    public function __construct(
        private BlizzardConnector $blizzard,
    ) {}

    /**
     * Display the guild roster of the current game version, or an empty state when it has none.
     */
    public function index(): Response
    {
        $gameVersion = ResolveCurrentGameVersion::run();

        abort_if($gameVersion === null, 404);

        if (! $gameVersion->ownsCurrentRoster()) {
            return Inertia::render('Roster/Index', [
                'gameVersion' => new GameVersionResource($gameVersion),
                'hasRoster' => false,
            ]);
        }

        return Inertia::render('Roster/Index', [
            'gameVersion' => new GameVersionResource($gameVersion),
            'hasRoster' => true,
            'classes' => PlayableClassResource::collection($gameVersion->playableClasses()->orderBy('name')->get()),
            'ranks' => $gameVersion->guildRanks()->select('name')->ordered()->pluck('name')->unique()->values(),
            'races' => PlayableRaceResource::collection($gameVersion->playableRaces()->orderBy('name')->get()),
            'characters' => Inertia::defer(function () use ($gameVersion) {
                $members = $this->blizzard->send(new GetGuildRosterRequest(
                    $gameVersion->realm_slug,
                    $gameVersion->guild_slug,
                    $gameVersion->blizzard_namespace,
                ))->dto()->members ?? [];

                return new GuildRosterMemberCollection($members, $gameVersion);
            }),
        ]);
    }

    /**
     * Display a single character's overview.
     */
    public function show(Character $character, ?string $slug = null): Response|RedirectResponse
    {
        if ($slug !== $character->slug) {
            return redirect()->route('characters.show', [
                'character' => $character,
                'slug' => $character->slug,
            ], 303);
        }

        $character->load(['gameVersion', 'media', 'playableClass', 'playableRace', 'rank', 'specializations', 'linkedCharacters.playableClass', 'linkedCharacters.rank']);

        $gameVersion = $character->gameVersion;

        RefreshCharacterPortrait::queueIfStale($character);

        return Inertia::render('Roster/Characters/Show', [
            'character' => new CharacterResource($character),
            'roster_url' => $gameVersion?->ownsCurrentRoster()
                ? URL::route('roster.index', ['game_version' => $gameVersion->slug])
                : URL::route('roster.index'),
            'recent_reports' => Inertia::defer(fn () => $character->warcraftLogsReports()
                ->orderByDesc('start_time')
                ->limit(10)
                ->get()),
        ]);
    }

    /**
     * Display the edit form for a character.
     */
    #[Middleware('auth')]
    #[Authorize('update', 'character')]
    public function edit(Character $character, string $slug): Response
    {
        $character->load(['playableClass.specializations', 'specializations']);

        return Inertia::render('Manage/Characters/Edit', [
            'character' => new CharacterResource($character),
            'specializations' => PlayableSpecializationResource::collection(
                $character->playableClass->specializations()->orderBy('name')->get()
            ),
        ]);
    }

    /**
     * Persist character edits.
     *
     * Accepts partial payloads: each field is written only when the caller
     * supplied it, so the addon settings page can toggle the loot-councillor
     * flag without owning the character's specializations. Both writes run
     * inside a transaction so they succeed or fail together.
     */
    #[Middleware('auth')]
    #[Authorize('update', 'character')]
    public function update(UpdateCharacterRequest $request, Character $character): RedirectResponse
    {
        DB::transaction(function () use ($character, $request) {
            if ($request->has('is_loot_councillor')) {
                $isLootCouncillor = $request->boolean('is_loot_councillor');

                $character->update(['is_loot_councillor' => $isLootCouncillor]);
                $character->linkedCharacters()->update(['is_loot_councillor' => $isLootCouncillor]);
            }

            if ($request->has('specializations')) {
                $raidSpecId = $request->input('specializations.raid_specialization_id');

                $syncPayload = collect($request->input('specializations.specialization_ids', []))
                    ->mapWithKeys(fn ($id) => [$id => ['is_raid_spec' => (int) $id === (int) $raidSpecId]])
                    ->all();

                $character->specializations()->sync($syncPayload);
            }
        });

        return back()->with('success', 'Character updated.');
    }
}
