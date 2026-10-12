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
     * Show the form for logging an absence against one of the member's own
     * characters in their current game version.
     */
    #[Authorize('create', PlannedAbsence::class)]
    #[Middleware(RemembersCurrentGameVersion::class)]
    public function create(Request $request, BattleNetSession $battleNet): Response
    {
        $selectable = GameVersion::currentRosters()->values();
        $gameVersion = ResolveCurrentGameVersion::run($selectable);

        $battleNet->rememberIntendedUrl($request->fullUrlWithoutQuery(['character_portrait']));

        return Inertia::render('Account/Absences/Form', [
            'game_versions' => GameVersionResource::collection($selectable),
            'game_version' => $gameVersion ? new GameVersionResource($gameVersion) : null,
            'battle_net' => ['connected' => $battleNet->isConnected()],
            'account_characters' => Inertia::defer(fn (): array => $this->accountCharacters($battleNet, $gameVersion)),
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
    public function edit(PlannedAbsence $plannedAbsence): Response
    {
        $plannedAbsence->load(['character.playableRace', 'createdBy']);

        return Inertia::render('Account/Absences/Form', [
            'planned_absence' => new PlannedAbsenceResource($plannedAbsence),
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
     * The characters the member can pick, or why Battle.net couldn't list them.
     *
     * @return array{characters: AnonymousResourceCollection, mains_only: bool, error: array{code: BattleNetSessionError, message: string}|null}
     */
    private function accountCharacters(BattleNetSession $battleNet, ?GameVersion $gameVersion): array
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

        $characters = ResolveSelectableAccountCharacters::run($accountCharacters)->load('media');

        $characters->each(fn (Character $character) => RefreshCharacterPortrait::queueIfStale($character));

        return [
            'characters' => CharacterResource::collection($characters),
            'mains_only' => $characters->isEmpty() || $characters->contains('is_main', true),
            'error' => null,
        ];
    }
}
