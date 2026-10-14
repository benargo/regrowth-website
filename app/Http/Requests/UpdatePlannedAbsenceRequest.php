<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReportsGameVersionErrorsOnCharacter;
use App\Http\Requests\Concerns\ValidatesPlannedAbsenceDetails;
use App\Models\PlannedAbsence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlannedAbsenceRequest extends FormRequest
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
        /** @var PlannedAbsence|null $plannedAbsence */
        $plannedAbsence = $this->route('plannedAbsence');

        return [
            'game_version' => [
                'required_with:character',
                'integer',
                $this->releasedGameVersionRule(),
            ],
            'character' => [
                'sometimes',
                'integer',
                'min:1',
                Rule::exists('characters', 'id')
                    ->where('game_version_id', $this->integer('game_version'))
                    ->where('is_main', true),
            ],
            'user' => ['sometimes', 'nullable', 'string', Rule::exists('users', 'id')],
            ...$this->updateAbsenceDetailRules($plannedAbsence),
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
            'game_version.required_with' => 'A game version is required.',
            'game_version.integer' => 'The game version must be an ID.',
            'character.integer' => 'The character ID must be an integer.',
            'character.min' => 'The character ID must be at least 1.',
            'character.exists' => 'Choose a main character.',
            'user.exists' => 'Pick a Discord member and wait for them to finish loading.',
            ...$this->absenceDetailMessages(),
        ];
    }
}
