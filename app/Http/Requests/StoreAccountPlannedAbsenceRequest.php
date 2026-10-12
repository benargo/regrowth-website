<?php

namespace App\Http\Requests;

use App\Actions\Characters\FetchBattleNetAccountCharacters;
use App\Actions\Characters\ResolveSelectableAccountCharacters;
use App\Contracts\Http\SharesBattleNetSession;
use App\Http\Integrations\Blizzard\Exceptions\ApiException;
use App\Http\Requests\Concerns\ValidatesPlannedAbsenceDetails;
use App\Models\GameVersion;
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
    use ValidatesPlannedAbsenceDetails;

    public const string RECONNECT_MESSAGE = 'Your Battle.net connection has expired. Please connect again.';

    public const string UNAVAILABLE_MESSAGE = 'Battle.net is unavailable right now. Please try again shortly.';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'game_version' => ['required', 'integer', Rule::in(GameVersion::currentRosters()->modelKeys())],
            'character' => ['required', 'integer', 'min:1'],
            'user' => ['prohibited'],
            ...$this->createAbsenceDetailRules(),
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
            ...$this->absenceDetailMessages(),
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
