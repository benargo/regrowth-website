<?php

namespace App\Http\Controllers;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Data\PlannedAbsenceReasonData;
use App\Http\Middleware\RemembersCurrentGameVersion;
use App\Http\Requests\StorePlannedAbsenceRequest;
use App\Http\Requests\UpdatePlannedAbsenceRequest;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\PlannedAbsenceResource;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GameVersion;
use App\Models\PlannedAbsence;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Exceptions\UserNotInGuildException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

#[Middleware('auth')]
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
    #[Authorize('viewAny', PlannedAbsence::class)]
    public function index(Request $request): Response
    {
        return Inertia::render('Raiding/Absences/Index', [
            'planned_absences' => Inertia::defer(function () use ($request) {
                return Cache::tags(['attendance'])->remember('planned_absences:with_trashed', now()->addDay(), function () use ($request) {
                    return PlannedAbsenceResource::collection(
                        PlannedAbsence::query()
                            ->withTrashed()
                            ->with(['character.playableRace', 'createdBy'])
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
     * Show the form for logging an absence for any main character in the
     * officer's current game version.
     */
    #[Authorize('createForOthers', PlannedAbsence::class)]
    #[Middleware(RemembersCurrentGameVersion::class)]
    public function create(Request $request): Response
    {
        $selectable = $this->selectableGameVersions();
        $gameVersion = ResolveCurrentGameVersion::run($selectable);

        return Inertia::render('Raiding/Absences/Form', [
            'game_versions' => GameVersionResource::collection($selectable)->resolve($request),
            'game_version' => $gameVersion ? (new GameVersionResource($gameVersion))->resolve($request) : null,
            'characters' => $this->pickerCharacters($request, $gameVersion),
            'action' => route('raiding.absences.store'),
            'reasons' => PlannedAbsenceReasonData::options(),
            'discord_members' => Inertia::optional(fn (): array => $this->searchDiscordMembers($request)),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[Authorize('createForOthers', PlannedAbsence::class)]
    public function store(StorePlannedAbsenceRequest $request): RedirectResponse
    {
        if ($request->has('user')) {
            $user = $this->resolveUser($request->input('user'));
        }

        PlannedAbsence::create([
            'character_id' => $request->integer('character'),
            'user_id' => $user->id ?? null,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date', null),
            'reason' => $request->input('reason'),
            'notes' => $request->input('notes'),
            'created_by' => $request->user()->id,
        ]);

        return to_route('raiding.absences.index')->with('success', 'Planned absence created successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Edit & Update
    |--------------------------------------------------------------------------
    */

    /**
     * Show the form for editing the specified resource. The picker is limited
     * to the version of the absence's character.
     */
    #[Authorize('createForOthers', PlannedAbsence::class)]
    public function edit(Request $request, PlannedAbsence $plannedAbsence): Response
    {
        $plannedAbsence->load(['character.playableRace', 'character.gameVersion', 'createdBy']);

        $gameVersion = $plannedAbsence->character?->gameVersion
            ?? ResolveCurrentGameVersion::run($this->selectableGameVersions());

        return Inertia::render('Raiding/Absences/Form', [
            'planned_absence' => (new PlannedAbsenceResource($plannedAbsence))->resolve($request),
            'game_version' => $gameVersion ? (new GameVersionResource($gameVersion))->resolve($request) : null,
            'characters' => $this->pickerCharacters($request, $gameVersion),
            'action' => route('raiding.absences.update', $plannedAbsence),
            'reasons' => PlannedAbsenceReasonData::options(),
            'discord_members' => Inertia::optional(fn (): array => $this->searchDiscordMembers($request)),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    #[Authorize('createForOthers', PlannedAbsence::class)]
    public function update(UpdatePlannedAbsenceRequest $request, PlannedAbsence $plannedAbsence): RedirectResponse
    {
        $updates = [];

        if ($request->has('character')) {
            $updates = Arr::add($updates, 'character_id', $request->integer('character'));
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

        if ($request->has('notes')) {
            $updates = Arr::add($updates, 'notes', $request->input('notes'));
        }

        $plannedAbsence->update($updates);

        return to_route('raiding.absences.index')->with('success', 'Planned absence updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy & Restore
    |--------------------------------------------------------------------------
    */

    /**
     * Remove the specified resource from storage.
     */
    #[Authorize('deleteForOthers', PlannedAbsence::class)]
    public function destroy(PlannedAbsence $plannedAbsence): RedirectResponse
    {
        $plannedAbsence->delete();

        return to_route('raiding.absences.index')->with('success', 'Planned absence deleted successfully.');
    }

    /**
     * Restore the specified resource from storage.
     */
    #[Authorize('restore', 'plannedAbsence')]
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
     * Search the Discord server's members by username or nickname prefix,
     * for the user picker. Only evaluated when the page reloads the prop.
     *
     * @return array<int, array{id: string|null, nickname: string|null, username: string|null}>
     */
    private function searchDiscordMembers(Request $request): array
    {
        $validated = $request->validate([
            'member_search' => ['required', 'string'],
            'member_limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ]);

        $query = $validated['member_search'];
        $limit = (int) ($validated['member_limit'] ?? 1);

        // Cache plain arrays: cache.serializable_classes is false, so cached objects can't be unserialized.
        return Cache::tags(['discord'])->remember(
            'discord:members:search:'.mb_strtolower($query),
            now()->addMinutes(5),
            fn (): array => $this->discord->searchGuildMembers($query, $limit)
                ->map(fn ($member): array => [
                    'id' => $member->user?->id,
                    'nickname' => $member->nick,
                    'username' => $member->user?->username,
                ])
                ->all()
        );
    }

    /**
     * The versions an officer can log absences against: released versions,
     * newest first.
     *
     * @return Collection<int, GameVersion>
     */
    private function selectableGameVersions(): Collection
    {
        return GameVersion::released()->orderByDesc('release_date')->get();
    }

    /**
     * Every main character in the game version, for the picker.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pickerCharacters(Request $request, ?GameVersion $gameVersion): array
    {
        if ($gameVersion === null) {
            return [];
        }

        return Cache::tags(['characters'])->remember(
            "characters:game_version:{$gameVersion->id}:planned_absence_mains",
            now()->addDay(),
            fn (): array => CharacterResource::collection(
                Character::where('game_version_id', $gameVersion->id)
                    ->where('is_main', true)
                    ->with(['playableRace', 'playableClass'])
                    ->orderBy('name')
                    ->get()
            )->resolve($request),
        );
    }
}
