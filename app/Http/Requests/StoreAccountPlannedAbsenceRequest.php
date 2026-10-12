<?php

namespace App\Http\Requests;

use App\Actions\Characters\FetchBattleNetAccountCharacters;
use App\Actions\Characters\ResolveSelectableAccountCharacters;
use App\Contracts\Http\SharesBattleNetSession;
use App\Enums\PlannedAbsenceReason;
use App\Http\Integrations\Blizzard\Exceptions\ApiException;
use App\Models\GameVersion;
use App\Models\PlannedAbsence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Saloon\Exceptions\Request\FatalRequestException;

/**
 * A member logging an absence for themselves against a character on their
 * Battle.net account.
 */
class StoreAccountPlannedAbsenceRequest extends FormRequest
{
    public const string RECONNECT_MESSAGE = 'Your Battle.net connection has expired. Please connect again.';

    public const string UNAVAILABLE_MESSAGE = 'Battle.net is unavailable right now. Please try again shortly.';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $startDateRules = ['required', 'date'];
        if (! $this->user()?->can('createBackdated', PlannedAbsence::class)) {
            $startDateRules[] = 'after_or_equal:today';
        }

        return [
            'game_version' => ['required', 'integer', Rule::in(GameVersion::currentRosters()->modelKeys())],
            'character' => ['required', 'integer', 'min:1'],
            'user' => ['prohibited'],
            'start_date' => $startDateRules,
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'reason' => ['required', Rule::enum(PlannedAbsenceReason::class)],
            'notes' => [
                'nullable',
                'string',
                Rule::requiredIf($this->input('reason') === PlannedAbsenceReason::Other->value),
            ],
        ];
    }

    /**
     * Check the character against the member's Battle.net account once the
     * version and character are otherwise valid.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['game_version', 'character'])) {
                    return;
                }

                $this->checkAccountCharacter($validator);
            },
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'game_version.required' => 'A game version is required.',
            'game_version.integer' => 'The game version must be an ID.',
            'game_version.in' => "That game version isn't available.",
            'character.required' => 'A character is required.',
            'character.integer' => 'The character ID must be an integer.',
            'character.min' => 'The character ID must be at least 1.',
            'user.prohibited' => 'You can only log absences for yourself here.',
            'start_date.required' => 'A start date is required.',
            'start_date.date' => 'The start date must be a valid date.',
            'start_date.after_or_equal' => 'The start date must not be in the past.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after' => 'The end date must be after the start date.',
            'reason.required' => 'Choose a reason.',
            'reason.enum' => 'Choose one of the listed reasons.',
            'notes.required' => 'Add a note when the reason is Other.',
            'notes.string' => 'The notes must be text.',
        ];
    }

    private function checkAccountCharacter(Validator $validator): void
    {
        $authenticator = session(SharesBattleNetSession::AUTHENTICATOR_KEY);
        $accessToken = $authenticator?->hasNotExpired() ? $authenticator->getAccessToken() : null;

        if ($accessToken === null) {
            $validator->errors()->add('character', self::RECONNECT_MESSAGE);

            return;
        }

        try {
            $accountCharacters = FetchBattleNetAccountCharacters::run($accessToken, GameVersion::find($this->integer('game_version')));
        } catch (ApiException $exception) {
            if ($exception->getResponse()->status() === 401) {
                session()->forget(SharesBattleNetSession::AUTHENTICATOR_KEY);
                $validator->errors()->add('character', self::RECONNECT_MESSAGE);

                return;
            }

            $validator->errors()->add('character', self::UNAVAILABLE_MESSAGE);

            return;
        } catch (FatalRequestException) {
            $validator->errors()->add('character', self::UNAVAILABLE_MESSAGE);

            return;
        }

        if (! $accountCharacters->contains('id', $this->integer('character'))) {
            $validator->errors()->add('character', "That character isn't on your Battle.net account.");

            return;
        }

        if (! ResolveSelectableAccountCharacters::run($accountCharacters)->contains('id', $this->integer('character'))) {
            $validator->errors()->add('character', 'Choose a main character.');
        }
    }
}
