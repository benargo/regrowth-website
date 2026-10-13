<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

/**
 * For form requests where the game version has no field of its own and is
 * chosen along with the character: any game version error is reported on
 * `character`, unless the character already has an error of its own.
 */
trait ReportsGameVersionErrorsOnCharacter
{
    /**
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        if ($errors->has('game_version')) {
            $gameVersionMessages = $errors->get('game_version');
            $errors->forget('game_version');

            if (! $errors->has('character')) {
                foreach ($gameVersionMessages as $message) {
                    $errors->add('character', $message);
                }
            }
        }

        parent::failedValidation($validator);
    }
}
