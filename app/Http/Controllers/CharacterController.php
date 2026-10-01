<?php

namespace App\Http\Controllers;

use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Requests\UpdateCharacterRequest;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\GuildRosterMemberCollection;
use App\Http\Resources\PlayableClassResource;
use App\Http\Resources\PlayableRaceResource;
use App\Http\Resources\PlayableSpecializationResource;
use App\Jobs\AttachPortraitToCharacter;
use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CharacterController extends Controller
{
    public function __construct(
        private BlizzardConnector $blizzard,
    ) {}

    /**
     * Redirect the legacy roster URL to the default game version's roster.
     */
    public function redirectToDefaultRoster(): RedirectResponse
    {
        $gameVersion = GameVersion::defaultRoster();

        abort_if($gameVersion === null, 404);

        return redirect()->route('roster.index', $gameVersion, 303);
    }

    /**
     * Display a game version's guild roster.
     */
    public function index(Request $request, GameVersion $gameVersion): Response
    {
        $rosters = GameVersion::currentRosters();

        abort_unless($rosters->contains($gameVersion), 404);

        return Inertia::render('Roster/Index', [
            'gameVersion' => (new GameVersionResource($gameVersion))->resolve($request),
            'gameVersions' => GameVersionResource::collection($rosters)->resolve($request),
            'classes' => PlayableClassResource::collection($gameVersion->playableClasses()->orderBy('name')->get())->resolve($request),
            'ranks' => $gameVersion->guildRanks()->select('name')->ordered()->pluck('name')->unique()->values(),
            'races' => PlayableRaceResource::collection($gameVersion->playableRaces()->orderBy('name')->get())->resolve($request),
            'characters' => Inertia::defer(function () use ($request, $gameVersion) {
                $members = $this->blizzard->send(new GetGuildRosterRequest(
                    $gameVersion->realm_slug,
                    $gameVersion->guild_slug,
                    $gameVersion->blizzard_namespace,
                ))->dto()->members ?? [];

                return (new GuildRosterMemberCollection($members, $gameVersion))->resolve($request);
            }),
        ]);
    }

    /**
     * Display a single character's overview.
     */
    public function show(Request $request, Character $character, ?string $slug = null): Response|RedirectResponse
    {
        if ($slug !== $character->slug) {
            return redirect()->route('characters.show', [
                'character' => $character,
                'slug' => $character->slug,
            ], 303);
        }

        $character->load(['gameVersion', 'playableClass', 'playableRace', 'rank', 'specializations', 'linkedCharacters.playableClass', 'linkedCharacters.rank']);

        $gameVersion = $character->gameVersion;
        $realmSlug = $gameVersion?->realm_slug;

        if ($realmSlug !== null && ! $character->hasMedia(HasCharacterMedia::MEDIA_COLLECTION)) {
            try {
                $dto = $this->blizzard->send(new GetCharacterMediaRequest(
                    $realmSlug,
                    $character->name,
                    $gameVersion->blizzard_namespace,
                ))->dto();

                $avatarAsset = collect($dto->assets)->first(fn ($asset) => $asset->key === 'avatar');

                if ($avatarAsset !== null) {
                    AttachPortraitToCharacter::dispatch($character->id, $avatarAsset->value);
                }
            } catch (\Throwable) {
                // Blizzard outage must not break the page render.
            }
        }

        return Inertia::render('Roster/Characters/Show', [
            'character' => (new CharacterResource($character))->resolve($request),
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
    public function edit(Request $request, Character $character, string $slug): Response
    {
        $character->load(['playableClass.specializations', 'specializations']);

        return Inertia::render('Manage/Characters/Edit', [
            'character' => (new CharacterResource($character))->resolve($request),
            'specializations' => PlayableSpecializationResource::collection(
                $character->playableClass->specializations()->orderBy('name')->get()
            )->resolve($request),
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
