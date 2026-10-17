<?php

namespace App\Http\Requests;

use App\Models\GameVersion;
use App\Models\Phase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StorePhaseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', ...$this->numberFormatRules(), $this->uniqueNumberRule($this->input('game_version_id'))],
            'description' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'game_version_id' => ['nullable', 'integer', Rule::exists(GameVersion::class, 'id')],
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
            'number.required' => 'Enter the phase number.',
            'number.unique' => 'Another phase in this game version already has that number.',
            'description.required' => 'Enter a description for the phase.',
            'start_date.date' => 'Enter a valid date and time.',
            'game_version_id.exists' => 'Choose one of the listed game versions.',
        ];
    }

    /**
     * Get the human-readable field names used in default messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'number' => 'phase number',
            'start_date' => 'start date',
            'game_version_id' => 'game version',
        ];
    }

    /**
     * The format rules for a phase number: up to 9.9, with at most one decimal place.
     *
     * @return list<string>
     */
    protected function numberFormatRules(): array
    {
        return ['numeric', 'decimal:0,1', 'between:0,9.9'];
    }

    /**
     * The rule that keeps a phase number unique within a game version, where
     * phases with no game version share one scope.
     */
    protected function uniqueNumberRule(mixed $gameVersionId): Unique
    {
        return Rule::unique(Phase::class, 'number')
            ->where('game_version_id', is_numeric($gameVersionId) ? (int) $gameVersionId : null);
    }
}
