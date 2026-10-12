<?php

namespace App\Http\Controllers;

use App\Actions\Characters\RefreshCharacterPortrait;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Contracts\HasCharacterMedia;
use App\Data\PlannedAbsenceReasonData;
use App\Http\Controllers\Concerns\SharesCharacterPortrait;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

#[Middleware('auth')]
#[Authorize('viewAny', PlannedAbsence::class)]
class PlannedAbsenceController extends Controller
{
    use SharesCharacterPortrait;

    public function __construct(
        protected Discord $discord
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Raiding/Absences/Index', [
            'planned_absences' => Inertia::defer(function () use ($request) {
                $absences = PlannedAbsence::query()
                    ->withTrashed()
                    ->notEnded()
                    ->with(['character.media', 'character.playableClass', 'createdBy'])
                    ->orderBy('start_date')
                    ->get();

                $absences
                    ->reject(fn (PlannedAbsence $absence) => $absence->trashed())
                    ->pluck('character')->filter()->unique('id')
                    ->each(fn (Character $character) => RefreshCharacterPortrait::queueIfStale($character));

                return PlannedAbsenceResource::collectionForOfficerList($absences)
                    ->additional(['permissions' => $this->officerPermissions($request->user())]);
            }),
            'past_planned_absences' => Inertia::defer(fn (): array => $this->pastAbsences($request)),
            'character_portraits' => Inertia::optional(fn (): object => $this->characterPortraits()),
        ]);
    }

    /**
     * Get the past absences for the officer table.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pastAbsences(Request $request): array
    {
        return Cache::tags(['attendance'])->remember(
            'planned_absences:past',
            now()->endOfDay(),
            fn (): array => PlannedAbsenceResource::collectionForOfficerList(
                PlannedAbsence::query()
                    ->withTrashed()
                    ->ended()
                    ->with(['character.media', 'character.playableClass', 'createdBy'])
                    ->orderBy('start_date')
                    ->get()
            )->resolve($request)
        );
    }

    /**
     * The officer's permissions, which apply to every absence on the index.
     * Computed per request and kept out of the cached past absences.
     *
     * @return array{update: bool, delete: bool, restore: bool}
     */
    private function officerPermissions(User $user): array
    {
        return [
            'update' => $user->isAuthorizedTo('update-planned-absences'),
            'delete' => $user->isAuthorizedTo('delete-planned-absences'),
            'restore' => $user->can('restore', PlannedAbsence::class),
        ];
    }

    /**
     * Get the portrait URL of each character with a current or upcoming
     * absence, keyed by character ID.
     */
    private function characterPortraits(): object
    {
        return (object) Character::query()
            ->whereHas('plannedAbsences', fn (Builder $query) => $query->notEnded())
            ->with('media')
            ->get()
            ->mapWithKeys(fn (Character $character): array => [
                $character->id => $character->getFirstMediaUrl(HasCharacterMedia::MEDIA_COLLECTION) ?: null,
            ])
            ->all();
    }

    /**
     * Show the form for creating a new resource.
     */
    #[Authorize('create', PlannedAbsence::class)]
    #[Middleware(RemembersCurrentGameVersion::class)]
    public function create(Request $request): Response
    {
        $selectable = function () use (&$selectableVersions): Collection {
            return $selectableVersions ??= $this->selectableGameVersions();
        };
        $gameVersion = function () use (&$resolvedVersion, $selectable): ?GameVersion {
            return $resolvedVersion ??= ResolveCurrentGameVersion::run($selectable());
        };

        return Inertia::render('Raiding/Absences/Form', [
            'game_versions' => fn () => GameVersionResource::collection($selectable()),
            'game_version' => fn () => $gameVersion() ? new GameVersionResource($gameVersion()) : null,
            'characters' => fn () => $this->pickerCharacters($gameVersion()),
            'action' => route('raiding.absences.store'),
            'reasons' => fn () => PlannedAbsenceReasonData::options(),
            'discord_members' => Inertia::optional(fn (): array => $this->searchDiscordMembers($request)),
            'character_portrait' => Inertia::optional(fn (): array => $this->characterPortrait($request)),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[Authorize('create', PlannedAbsence::class)]
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

    /**
     * Show the form for editing the specified resource.
     */
    #[Authorize('update', 'plannedAbsence')]
    public function edit(Request $request, PlannedAbsence $plannedAbsence): Response
    {
        $loadedAbsence = function () use (&$loaded, $plannedAbsence): PlannedAbsence {
            return $loaded ??= $plannedAbsence->load(['character.playableRace', 'character.gameVersion', 'user', 'createdBy']);
        };
        $gameVersion = function () use (&$resolvedVersion, $loadedAbsence): ?GameVersion {
            return $resolvedVersion ??= $loadedAbsence()->character?->gameVersion
                ?? ResolveCurrentGameVersion::run($this->selectableGameVersions());
        };

        return Inertia::render('Raiding/Absences/Form', [
            'planned_absence' => fn () => new PlannedAbsenceResource($loadedAbsence()),
            'game_version' => fn () => $gameVersion() ? new GameVersionResource($gameVersion()) : null,
            'characters' => fn () => $this->pickerCharacters($gameVersion()),
            'action' => route('raiding.absences.update', $plannedAbsence),
            'reasons' => fn () => PlannedAbsenceReasonData::options(),
            'discord_members' => Inertia::optional(fn (): array => $this->searchDiscordMembers($request)),
            'character_portrait' => Inertia::optional(fn (): array => $this->characterPortrait($request)),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    #[Authorize('update', 'plannedAbsence')]
    public function update(UpdatePlannedAbsenceRequest $request, PlannedAbsence $plannedAbsence): RedirectResponse
    {
        $updates = $request->safe()->only(['start_date', 'end_date', 'reason', 'notes']);

        if ($request->has('character')) {
            $updates['character_id'] = $request->integer('character');
        }

        if ($request->has('user')) {
            $updates['user_id'] = $this->resolveUser($request->input('user'))->id;
        }

        $plannedAbsence->update($updates);

        return to_route('raiding.absences.index')->with('success', 'Planned absence updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    #[Authorize('delete', 'plannedAbsence')]
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

    /**
     * Find a user by ID, or create one from their Discord guild member data.
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
     * Search the Discord server's members by username or nickname prefix.
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
            "discord:members:search:{$limit}:".mb_strtolower($query),
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
     * Get the released game versions, newest first.
     *
     * @return Collection<int, GameVersion>
     */
    private function selectableGameVersions(): Collection
    {
        return GameVersion::released()->orderByDesc('release_date')->get();
    }

    /**
     * Get every main character in the game version.
     */
    private function pickerCharacters(?GameVersion $gameVersion): AnonymousResourceCollection
    {
        if ($gameVersion === null) {
            return CharacterResource::collection(collect());
        }

        return CharacterResource::collection(
            Character::where('game_version_id', $gameVersion->id)
                ->where('is_main', true)
                ->with(['playableRace', 'playableClass'])
                ->orderBy('name')
                ->get()
        );
    }
}
