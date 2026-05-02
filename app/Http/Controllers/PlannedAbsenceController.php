<?php

namespace App\Http\Controllers;

use App\Exceptions\CharacterNotMainException;
use App\Exceptions\MultipleCharactersFoundException;
use App\Http\Requests\PlannedAbsences\StorePlannedAbsenceRequest;
use App\Http\Requests\PlannedAbsences\UpdatePlannedAbsenceRequest;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\PlannedAbsenceResource;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\PlannedAbsence;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Exceptions\UserNotInGuildException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Normalizer;

class PlannedAbsenceController extends Controller
{
    public function __construct(
        protected Discord $discord
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Raiding/PlannedAbsences/Index', [
            'planned_absences' => Inertia::defer(function () use ($request) {
                return Cache::tags(['attendance'])->remember('planned_absences:with_trashed', now()->addDay(), function () use ($request) {
                    return PlannedAbsenceResource::collection(
                        PlannedAbsence::query()
                            ->withTrashed()
                            ->with(['character', 'createdBy'])
                            ->orderBy('start_date')
                            ->get()
                    )->resolve($request);
                });
            }),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create & Store
    |--------------------------------------------------------------------------
    */

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $characters = $this->buildCharactersResourceCollection($request);

        $resolvedCharacter = $request->user()->cannot('createForOthers', PlannedAbsence::class)
            ? $this->resolveCharacterFromUserNickname($request->user())
            : null;

        return Inertia::render('Raiding/PlannedAbsences/Form', [
            'characters' => $characters,
            'resolved_character' => $resolvedCharacter ? new CharacterResource($resolvedCharacter)->resolve($request) : null,
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

        return $this->redirectAfterModification($request, 'Planned absence created successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Edit & Update
    |--------------------------------------------------------------------------
    */

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, PlannedAbsence $plannedAbsence): Response
    {
        $characters = $this->buildCharactersResourceCollection($request);

        $plannedAbsence->load(['character', 'createdBy']);

        $resolvedCharacter = $request->user()->cannot('createForOthers', PlannedAbsence::class)
            ? $this->resolveCharacterFromUserNickname($request->user())
            : null;

        return Inertia::render('Raiding/PlannedAbsences/Form', [
            'characters' => $characters,
            'planned_absence' => new PlannedAbsenceResource($plannedAbsence)->resolve($request),
            'resolved_character' => $resolvedCharacter ? new CharacterResource($resolvedCharacter)->resolve($request) : null,
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

        return $this->redirectAfterModification($request, 'Planned absence updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy & Restore
    |--------------------------------------------------------------------------
    */

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, PlannedAbsence $plannedAbsence): RedirectResponse
    {
        $plannedAbsence->delete();

        return $this->redirectAfterModification($request, 'Planned absence deleted successfully.');
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore(Request $request, PlannedAbsence $plannedAbsence): RedirectResponse
    {
        if ($plannedAbsence === null) {
            return back()->with(['error' => 'Planned absence not found.']);
        }

        if (! $plannedAbsence->trashed()) {
            return back()->with(['error' => 'Planned absence is not deleted.']);
        }

        $plannedAbsence->restore();

        return back()->with('success', 'Planned absence restored successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine where to redirect the user after creating/updating a planned absence, based on their permissions
     */
    private function redirectAfterModification(Request $request, string $successMessage = 'Success'): RedirectResponse
    {
        if ($request->user()->can('viewAny', PlannedAbsence::class)) {
            return to_route('raids.absences.index')->with('success', $successMessage);
        }

        return to_route('account.index')->with('success', $successMessage);
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
     * Attempt to resolve a main Character from the first word of the user's Discord nickname.
     *
     * Returns null when the nickname is absent, or when zero or multiple characters match
     * (to avoid ambiguity, the user must pick manually in those cases).
     */
    private function resolveCharacterFromUserNickname(User $user): ?Character
    {
        if ($user->nickname === null) {
            return null;
        }

        $firstWord = explode(' ', trim($user->nickname))[0];
        $normalized = $this->normalizeCharacterName($firstWord);

        $matches = Character::query()
            ->where('is_main', true)
            ->get()
            ->filter(fn (Character $c) => $this->normalizeCharacterName($c->name) === $normalized);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Resolve a user based on the provided identifier.
     *
     * The user may already exist in the database, but if not we should check the Discord API
     * to find a matching user and create a new user record.
     *
     * @throws UserNotInGuildException
     * @throws \Exception
     */
    private function resolveUser(string $userIdentifier): User
    {
        $user = User::find($userIdentifier);

        if ($user) {
            return $user;
        }

        // If the user doesn't exist in our database, attempt to fetch them from the Discord API
        $member = $this->discord->getGuildMember($userIdentifier);

        // If we successfully fetch the guild member data, create a new user record in our database
        $user = User::create([
            'id' => $userIdentifier,
            'username' => $member->user?->username,
            'discriminator' => $member->user?->discriminator ?? '0',
            'nickname' => $member->nick,
            'avatar' => $member->user?->avatar,
            'guild_avatar' => $member->avatar,
            'banner' => $member->banner,
        ]);

        $incomingRoleIds = $member->roles;
        $recognizedRoleIds = DiscordRole::whereIn('id', $incomingRoleIds)->pluck('id')->toArray();
        $user->discordRoles()->sync($recognizedRoleIds);

        return $user;
    }

    /**
     * Build a resource collection of main characters for use in the create/edit forms.
     */
    private function buildCharactersResourceCollection(Request $request): array
    {
        return Cache::tags(['characters'])->remember('characters:mains:to_resource_collection', now()->addDay(), function () use ($request) {
            return CharacterResource::collection(
                Character::query()
                    ->where('is_main', true)
                    ->orderBy('name')
                    ->get()
            )->resolve($request);
        });
    }
}
