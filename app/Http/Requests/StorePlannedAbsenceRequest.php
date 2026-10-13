<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReportsGameVersionErrorsOnCharacter;
use App\Http\Requests\Concerns\ValidatesPlannedAbsenceDetails;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlannedAbsenceRequest extends FormRequest
{
    use ReportsGameVersionErrorsOnCharacter;
    use ValidatesPlannedAbsenceDetails;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'game_version' => [
                'required',
                'integer',
                $this->releasedGameVersionRule(),
            ],
            'character' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('characters', 'id')
                    ->where('game_version_id', $this->integer('game_version'))
                    ->where('is_main', true),
            ],
            'user' => ['sometimes', 'nullable', 'string'],
            ...$this->createAbsenceDetailRules(),
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
            'character.required' => 'A character is required.',
            'character.integer' => 'The character ID must be an integer.',
            'character.min' => 'The character ID must be at least 1.',
            'character.exists' => 'Choose a main character.',
            ...$this->absenceDetailMessages(),
        ];
    }
}
