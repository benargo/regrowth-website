<?php

namespace App\Http\Requests;

use App\Actions\Characters\FetchBattleNetAccountCharacters;
use App\Actions\Characters\ResolveSelectableAccountCharacters;
use App\Http\Integrations\Blizzard\Exceptions\BattleNetSessionException;
use App\Http\Integrations\Blizzard\Support\BattleNetSession;
use App\Http\Requests\Concerns\ValidatesPlannedAbsenceDetails;
use App\Models\GameVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A member logging an absence for themselves against a character on their
 * Battle.net account.
 */
class StoreAccountPlannedAbsenceRequest extends FormRequest
{
    use ValidatesPlannedAbsenceDetails;

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
    public function after(BattleNetSession $battleNet): array
    {
        return [
            function (Validator $validator) use ($battleNet): void {
                if ($validator->errors()->hasAny(['game_version', 'character'])) {
                    return;
                }

                $this->checkAccountCharacter($validator, $battleNet);
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

    private function checkAccountCharacter(Validator $validator, BattleNetSession $battleNet): void
    {
        $gameVersion = GameVersion::find($this->integer('game_version'));

        try {
            $accountCharacters = $battleNet->withAccessToken(fn (string $accessToken) => FetchBattleNetAccountCharacters::run($accessToken, $gameVersion));
        } catch (BattleNetSessionException $exception) {
            $validator->errors()->add('character', $exception->error->message());

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
