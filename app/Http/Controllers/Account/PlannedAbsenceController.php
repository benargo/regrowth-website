<?php

namespace App\Http\Controllers\Account;

use App\Actions\Characters\FetchBattleNetAccountCharacters;
use App\Actions\Characters\RefreshCharacterPortrait;
use App\Actions\Characters\ResolveSelectableAccountCharacters;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Contracts\Http\SharesBattleNetSession;
use App\Data\PlannedAbsenceReasonData;
use App\Http\Controllers\Concerns\SharesCharacterPortrait;
use App\Http\Controllers\Controller;
use App\Http\Integrations\Blizzard\Exceptions\ApiException;
use App\Http\Middleware\RemembersCurrentGameVersion;
use App\Http\Requests\StoreAccountPlannedAbsenceRequest;
use App\Http\Requests\UpdateAccountPlannedAbsenceRequest;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\PlannedAbsenceResource;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\PlannedAbsence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Saloon\Exceptions\Request\FatalRequestException;

/**
 * Lets members log, edit and delete their own planned absences against the
 * characters on their Battle.net account.
 */
#[Middleware('auth')]
class PlannedAbsenceController extends Controller
{
    use SharesCharacterPortrait;

    /**
     * Show the form for logging an absence against one of the member's own
     * characters in their current game version.
     */
    #[Authorize('create', PlannedAbsence::class)]
    #[Middleware(RemembersCurrentGameVersion::class)]
    public function create(Request $request): Response
    {
        $selectable = GameVersion::currentRosters()->values();
        $gameVersion = ResolveCurrentGameVersion::run($selectable);
        $accessToken = $this->battleNetAccessToken();

        session()->put(SharesBattleNetSession::INTENDED_URL_KEY, $request->fullUrl());

        return Inertia::render('Account/Absences/Form', [
            'game_versions' => GameVersionResource::collection($selectable)->resolve($request),
            'game_version' => $gameVersion ? (new GameVersionResource($gameVersion))->resolve($request) : null,
            'battle_net' => ['connected' => $accessToken !== null],
            'account_characters' => Inertia::defer(fn (): array => $this->accountCharacters($request, $accessToken, $gameVersion)),
            'action' => route('account.absences.store'),
            'reasons' => PlannedAbsenceReasonData::options(),
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
     * Show the form for changing an absence's dates and reason. The character
     * is read-only, so Battle.net isn't needed.
     */
    #[Authorize('update', 'plannedAbsence')]
    public function edit(Request $request, PlannedAbsence $plannedAbsence): Response
    {
        $plannedAbsence->load(['character.playableRace', 'createdBy']);

        return Inertia::render('Account/Absences/Form', [
            'planned_absence' => (new PlannedAbsenceResource($plannedAbsence))->resolve($request),
            'action' => route('account.absences.update', $plannedAbsence),
            'reasons' => PlannedAbsenceReasonData::options(),
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
     * Delete one of the member's own absences before it starts.
     */
    #[Authorize('delete', 'plannedAbsence')]
    public function destroy(PlannedAbsence $plannedAbsence): RedirectResponse
    {
        $plannedAbsence->delete();

        return to_route('account.index')->with('success', 'Planned absence deleted successfully.');
    }

    /**
     * The member's Battle.net access token, while it is still valid.
     */
    private function battleNetAccessToken(): ?string
    {
        $authenticator = session(SharesBattleNetSession::AUTHENTICATOR_KEY);

        return $authenticator?->hasNotExpired() ? $authenticator->getAccessToken() : null;
    }

    /**
     * The characters the member can pick, or why Battle.net couldn't list them.
     *
     * @return array{characters: array<int, mixed>, mains_only: bool, error: 'reconnect'|'unavailable'|null}
     */
    private function accountCharacters(Request $request, ?string $accessToken, ?GameVersion $gameVersion): array
    {
        if ($accessToken === null || $gameVersion === null) {
            return ['characters' => [], 'mains_only' => true, 'error' => null];
        }

        try {
            $accountCharacters = FetchBattleNetAccountCharacters::run($accessToken, $gameVersion);
        } catch (ApiException $exception) {
            if ($exception->getResponse()->status() === 401) {
                session()->forget(SharesBattleNetSession::AUTHENTICATOR_KEY);

                return ['characters' => [], 'mains_only' => true, 'error' => 'reconnect'];
            }

            Log::warning('Battle.net account characters unavailable.', ['status' => $exception->getResponse()->status()]);

            return ['characters' => [], 'mains_only' => true, 'error' => 'unavailable'];
        } catch (FatalRequestException $exception) {
            Log::warning('Battle.net account characters unavailable.', ['message' => $exception->getMessage()]);

            return ['characters' => [], 'mains_only' => true, 'error' => 'unavailable'];
        }

        $characters = ResolveSelectableAccountCharacters::run($accountCharacters)->load('media');

        $characters->each(fn (Character $character) => RefreshCharacterPortrait::queueIfStale($character));

        return [
            'characters' => CharacterResource::collection($characters)->resolve($request),
            'mains_only' => $characters->isEmpty() || $characters->contains('is_main', true),
            'error' => null,
        ];
    }
}
