<?php

namespace App\Http\Controllers;

use App\Exceptions\CharacterNotMainException;
use App\Exceptions\MultipleCharactersFoundException;
use App\Http\Requests\PlannedAbsences\StorePlannedAbsenceRequest;
use App\Http\Requests\PlannedAbsences\UpdatePlannedAbsenceRequest;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\PlannedAbsenceResource;
use App\Models\Character;
use App\Models\PlannedAbsence;
use App\Models\User;
use App\Services\Discord\DiscordGuildService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Normalizer;

class PlannedAbsenceController extends Controller
{
    public function __construct(
        protected DiscordGuildService $discordGuildService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('Raids/PlannedAbsences/Index', [
            'plannedAbsences' => Inertia::defer(function () {
                return Cache::tags(['planned_absences'])->remember('planned_absences.index', now()->addDay(), function () {
                    return PlannedAbsenceResource::collection(
                        PlannedAbsence::query()
                            ->with(['character', 'createdBy'])
                            ->orderBy('start_date')
                            ->get()
                    );
                });
            }),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Raids/PlannedAbsences/Form', [
            'characters' => CharacterResource::collection(
                Character::query()
                    ->where('is_main', true)
                    ->orderBy('name')
                    ->get()
            ),
            'action' => route('raids.absences.store'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @throws MultipleCharactersFoundException
     * @throws CharacterNotMainException
     */
    public function store(StorePlannedAbsenceRequest $request): RedirectResponse
    {
        $input = $request->input('character');

        if (is_numeric($input)) {
            $character = Character::find((int) $input);
        } else {
            $normalizedInput = $this->normalizeCharacterName($input);

            $matches = Character::all()->filter(
                fn (Character $c) => $this->normalizeCharacterName($c->name) === $normalizedInput
            );

            if ($matches->count() > 1) {
                throw new MultipleCharactersFoundException($matches);
            }

            $character = $matches->first();
        }

        if ($character === null) {
            return back()->withErrors(['character' => 'Character not found.']);
        }

        if (! $character->is_main) {
            throw new CharacterNotMainException($character);
        }

        if ($request->has('user')) {
            $user = $this->resolveUser($request->input('user'));
        }

        PlannedAbsence::create([
            'character_id' => $character->id,
            'user_id' => $user->id ?? null,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date', null),
            'reason' => $request->input('reason'),
            'created_by' => $request->user()->id,
        ]);

        if ($request->user()->can('viewAny', PlannedAbsence::class)) {
            return to_route('raids.absences.index')->with('success', 'Planned absence created successfully.');
        }

        return to_route('account.index')->with('success', 'Planned absence created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PlannedAbsence $plannedAbsence): Response
    {
        $plannedAbsence->load(['character', 'createdBy']);

        return Inertia::render('Raids/PlannedAbsences/Form', [
            'characters' => CharacterResource::collection(
                Character::query()
                    ->where('is_main', true)
                    ->orderBy('name')
                    ->get()
            ),
            'plannedAbsence' => new PlannedAbsenceResource($plannedAbsence),
            'action' => route('raids.absences.update', $plannedAbsence),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @throws CharacterNotMainException
     */
    public function update(UpdatePlannedAbsenceRequest $request, PlannedAbsence $plannedAbsence): RedirectResponse
    {
        $updates = [];

        if ($request->has('character')) {
            $character = $request->character();

            if (! $character->is_main) {
                throw new CharacterNotMainException($character);
            }

            $updates = Arr::add($updates, 'character_id', $character->id);
        }

        if ($request->has('user')) {
            $user = $this->resolveUser($request->input('user'));
            $updates = Arr::add($updates, 'user_id', $user->id);
        }

        if ($request->has('start_date')) {
            $updates = Arr::add($updates, 'start_date', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $updates = Arr::add($updates, 'end_date', $request->input('end_date', null));
        }

        if ($request->has('reason')) {
            $updates = Arr::add($updates, 'reason', $request->input('reason'));
        }

        $plannedAbsence->update($updates);

        return back()->with('success', 'Planned absence updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PlannedAbsence $plannedAbsence): RedirectResponse
    {
        $plannedAbsence->delete();

        return back()->with('success', 'Planned absence deleted successfully.');
    }

    /**
     * Normalize a character name by stripping diacritics and lowercasing,
     * so that "Deo", "Déo", and "Deò" all produce the same value.
     */
    private function normalizeCharacterName(string $name): string
    {
        $decomposed = Normalizer::normalize($name, Normalizer::FORM_KD);

        return mb_strtolower(preg_replace('/\p{Mn}/u', '', $decomposed));
    }

    /**
     * Resolve a user based on the provided identifier.
     *
     * The user may already exist in the database, but if not we should check the Discord API
     * to find a matching user and create a new user record.
     *
     * @throws \App\Services\Discord\Exceptions\UserNotInGuildException
     * @throws \Exception
     */
    private function resolveUser(string $userIdentifier): User
    {
        $user = User::find($userIdentifier);

        if ($user) {
            return $user;
        }

        // If the user doesn't exist in our database, attempt to fetch them from the Discord API
        $guildMemberData = $this->discordGuildService->getGuildMember($userIdentifier);

        // If we successfully fetch the guild member data, create a new user record in our database
        $user = User::create([
            'id' => $userIdentifier,
            'username' => Arr::get($guildMemberData, 'user.username', null),
            'discriminator' => Arr::get($guildMemberData, 'user.discriminator', '0'),
            'nickname' => Arr::get($guildMemberData, 'nick', null),
            'avatar' => Arr::get($guildMemberData, 'user.avatar', null),
            'guild_avatar' => Arr::get($guildMemberData, 'avatar', null),
            'banner' => Arr::get($guildMemberData, 'banner', null),
        ]);

        $incomingRoleIds = Arr::get($guildMemberData, 'roles', []);
        $recognizedRoleIds = DiscordRole::whereIn('id', $incomingRoleIds)->pluck('id')->toArray();
        $user->discordRoles()->sync($recognizedRoleIds);

        return $user;
    }
}
