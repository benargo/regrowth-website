<?php

namespace App\Http\Controllers;

use App\Enums\AllianceRaces;
use App\Http\Requests\UpdateCharacterRequest;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\PlayableClassResource;
use App\Http\Resources\PlayableSpecializationResource;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlayableClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CharacterController extends Controller
{
    /**
     * Display the guild roster.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Roster/Index', [
            'classes' => PlayableClassResource::collection(PlayableClass::all())->resolve($request),
            'ranks' => GuildRank::select('id', 'position', 'name')->orderBy('position')->get(),
            'races' => AllianceRaces::toArray(),
            'characters' => Inertia::defer(fn () => CharacterResource::collection(
                Character::with(['playableClass', 'rank', 'specializations'])
                    ->orderBy('name')
                    ->get()
            )->resolve($request)),
        ]);
    }

    /**
     * Display a single character's overview.
     */
    public function show(Request $request, Character $character, string $slug): Response
    {
        $character->load(['playableClass', 'rank', 'specializations', 'linkedCharacters.playableClass', 'linkedCharacters.rank']);

        return Inertia::render('Manage/Characters/Show', [
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
    #[Authorize('update', 'character')]
    public function edit(Request $request, Character $character, string $slug): Response
    {
        $character->load(['playableClass.specializations', 'specializations']);

        return Inertia::render('Manage/Characters/Edit', [
            'character' => (new CharacterResource($character))->resolve($request),
            'specializations' => PlayableSpecializationResource::collection(
                $character->playableClass->specializations
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

        return redirect()->route('management.characters.show', [
            'character' => $character,
            'slug' => $character->slug,
        ]);
    }
}
