<?php

namespace App\Http\Controllers\Account;

use App\Actions\Characters\FetchBattleNetAccountCharacters;
use App\Actions\Characters\RefreshCharacterPortrait;
use App\Actions\Characters\ResolveSelectableAccountCharacters;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Data\PlannedAbsenceReasonData;
use App\Http\Controllers\Concerns\SharesCharacterPortrait;
use App\Http\Controllers\Controller;
use App\Http\Integrations\Blizzard\Exceptions\BattleNetSessionException;
use App\Http\Integrations\Blizzard\Support\BattleNetSession;
use App\Http\Integrations\Blizzard\Support\BattleNetSessionError;
use App\Http\Requests\StoreAccountPlannedAbsenceRequest;
use App\Http\Requests\UpdateAccountPlannedAbsenceRequest;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\PlannedAbsenceResource;
use App\Jobs\AttachCharactersToUser;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\PlannedAbsence;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets members log, edit and delete their own planned absences against the
 * characters on their Battle.net account.
 */
#[Middleware('auth')]
class PlannedAbsenceController extends Controller
{
    use SharesCharacterPortrait;

    /**
     * Show the form for logging an absence.
     */
    #[Authorize('create', PlannedAbsence::class)]
    public function create(Request $request, BattleNetSession $battleNet): Response
    {
        $hasRoster = function () use (&$ownsRoster): bool {
            return $ownsRoster ??= ResolveCurrentGameVersion::run()?->ownsCurrentRoster() ?? false;
        };

        return Inertia::render('Account/Absences/Form', [
            'game_version' => fn (): ?GameVersionResource => ResolveCurrentGameVersion::run()?->toResource(),
            'has_roster' => $hasRoster,
            'battle_net' => function () use ($battleNet, $request): array {
                $battleNet->rememberIntendedUrl($request->fullUrlWithoutQuery(['character_portrait']));

                return ['connected' => $battleNet->isConnected()];
            },
            'account_characters' => Inertia::defer(fn (): array => $this->accountCharacters($battleNet, $hasRoster() ? ResolveCurrentGameVersion::run() : null, $request->user())),
            'action' => route('account.absences.store'),
            'reasons' => fn () => PlannedAbsenceReasonData::options(),
            'character_portrait' => Inertia::optional(fn (): array => $this->characterPortrait($request)),
        ]);
    }

    /**
     * Store an absence for the signed-in member.
     */
    #[Authorize('create', PlannedAbsence::class)]
    public function store(StoreAccountPlannedAbsenceRequest $request): RedirectResponse
    {
        PlannedAbsence::create([
            'character_id' => $request->integer('character'),
            'user_id' => $request->user()->id,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'reason' => $request->input('reason'),
            'notes' => $request->input('notes'),
            'created_by' => $request->user()->id,
        ]);

        return to_route('account.index')->with('success', 'Planned absence created successfully.');
    }

    /**
     * Show the form for editing an absence.
     */
    #[Authorize('update', 'plannedAbsence')]
    public function edit(PlannedAbsence $plannedAbsence): Response
    {
        return Inertia::render('Account/Absences/Form', [
            'planned_absence' => fn () => new PlannedAbsenceResource($plannedAbsence->load(['character.media', 'character.playableRace', 'createdBy'])),
            'action' => route('account.absences.update', $plannedAbsence),
            'reasons' => fn () => PlannedAbsenceReasonData::options(),
        ]);
    }

    /**
     * Update an absence's dates and reason.
     */
    #[Authorize('update', 'plannedAbsence')]
    public function update(UpdateAccountPlannedAbsenceRequest $request, PlannedAbsence $plannedAbsence): RedirectResponse
    {
        $plannedAbsence->update($request->safe()->only(['start_date', 'end_date', 'reason', 'notes']));

        return to_route('account.index')->with('success', 'Planned absence updated successfully.');
    }

    /**
     * Delete an absence.
     */
    #[Authorize('delete', 'plannedAbsence')]
    public function destroy(PlannedAbsence $plannedAbsence): RedirectResponse
    {
        $plannedAbsence->delete();

        return to_route('account.index')->with('success', 'Planned absence deleted successfully.');
    }

    /**
     * The member's selectable characters, or the Battle.net error.
     *
     * @return array{characters: AnonymousResourceCollection, mains_only: bool, error: array{code: BattleNetSessionError, message: string}|null}
     */
    private function accountCharacters(BattleNetSession $battleNet, ?GameVersion $gameVersion, User $user): array
    {
        if (! $battleNet->isConnected() || $gameVersion === null) {
            return ['characters' => CharacterResource::collection(collect()), 'mains_only' => true, 'error' => null];
        }

        try {
            $accountCharacters = $battleNet->withAccessToken(fn (string $accessToken) => FetchBattleNetAccountCharacters::run($accessToken, $gameVersion));
        } catch (BattleNetSessionException $exception) {
            return [
                'characters' => CharacterResource::collection(collect()),
                'mains_only' => true,
                'error' => ['code' => $exception->error, 'message' => $exception->error->message()],
            ];
        }

        $hasUnlinkedCharacters = $accountCharacters->contains(fn (Character $character): bool => $character->user_id !== $user->id);

        AttachCharactersToUser::dispatchIf($hasUnlinkedCharacters, $user->id, $accountCharacters);

        $characters = ResolveSelectableAccountCharacters::run($accountCharacters)->load('media');

        $characters->each(fn (Character $character) => RefreshCharacterPortrait::queueIfStale($character));

        return [
            'characters' => CharacterResource::collection($characters),
            'mains_only' => $characters->isEmpty() || $characters->contains('is_main', true),
            'error' => null,
        ];
    }
}
