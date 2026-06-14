<?php

namespace App\Http\Controllers;

use App\Contracts\HasCharacterMedia;
use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Requests\UpdateCharacterRequest;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\GuildRosterMemberCollection;
use App\Http\Resources\PlayableClassResource;
use App\Http\Resources\PlayableRaceResource;
use App\Http\Resources\PlayableSpecializationResource;
use App\Jobs\AttachPortraitToCharacter;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
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
     * Display the guild roster.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Roster/Index', [
            'classes' => PlayableClassResource::collection(PlayableClass::orderBy('name')->get())->resolve($request),
            'ranks' => GuildRank::select('name')->orderBy('position')->get()->pluck('name')->unique()->values(),
            'races' => PlayableRaceResource::collection(PlayableRace::where('faction', Faction::ALLIANCE)->orderBy('name')->get())->resolve($request),
            'filters' => [
                'search' => $request->input('filter.search'),
                'class_ids' => $request->input('filter.class_ids'),
                'race_ids' => $request->input('filter.race_ids'),
                'rank_names' => $request->input('filter.rank_names'),
                'known_only' => $request->input('filter.known_only'),
                'sort_column' => $request->query('sort_column', 'rank'),
                'sort_direction' => $request->query('sort_direction', 'asc'),
            ],
            'characters' => Inertia::defer(function () use ($request) {
                $members = $this->blizzard->send(new GetGuildRosterRequest(
                    $this->blizzard->defaultRealmSlug(),
                    $this->blizzard->defaultGuildSlug(),
                ))->dto()->members ?? [];

                return (new GuildRosterMemberCollection($members))->resolve($request);
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

        $character->load(['playableClass', 'playableRace', 'rank', 'specializations', 'linkedCharacters.playableClass', 'linkedCharacters.rank']);

        if (! $character->hasMedia(HasCharacterMedia::MEDIA_COLLECTION)) {
            try {
                $dto = $this->blizzard->send(new GetCharacterMediaRequest(
                    $this->blizzard->defaultRealmSlug(),
                    $character->name,
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
     * Syncs the character's specializations pivot, marking the chosen raid spec
     * via the `is_raid_spec` pivot column. Both the sync and the loot-councillor
     * update run inside a transaction so the two writes succeed or fail together.
     */
    #[Middleware('auth')]
    #[Authorize('update', 'character')]
    public function update(UpdateCharacterRequest $request, Character $character): RedirectResponse
    {
        $specializationIds = $request->input('specialization_ids', []);
        $raidSpecId = $request->input('raid_specialization_id');

        $syncPayload = collect($specializationIds)
            ->mapWithKeys(fn ($id) => [$id => ['is_raid_spec' => (int) $id === (int) $raidSpecId]])
            ->all();

        DB::transaction(function () use ($character, $syncPayload, $request) {
            $character->specializations()->sync($syncPayload);
            $character->update(['is_loot_councillor' => $request->boolean('is_loot_councillor')]);
        });

        return redirect()->route('characters.show', [
            'character' => $character,
            'slug' => $character->slug,
        ]);
    }
}
