<?php

namespace App\Http\Requests\PlannedAbsences;

use App\Models\Character;
use App\Models\PlannedAbsence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlannedAbsenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $plannedAbsence = $this->route('plannedAbsence');

        return $this->user()->can('update', $plannedAbsence)
            && (! $this->has('character') || $this->user()->hasPermissionViaDiscordRoles('update-planned-absences'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PlannedAbsence|null $plannedAbsence */
        $plannedAbsence = $this->route('plannedAbsence');
        $effectiveStartDate = $this->input('start_date') ?? $plannedAbsence?->start_date?->format('Y-m-d');

        return [
            'character' => ['sometimes', 'integer', 'min:1', Rule::exists('characters', 'id')],
            'user' => ['sometimes', 'nullable', 'string'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', ...($effectiveStartDate ? ['after:'.$effectiveStartDate] : [])],
            'reason' => ['sometimes', 'string'],
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
            'character.integer' => 'The character ID must be an integer.',
            'character.min' => 'The character ID must be at least 1.',
            'character.exists' => 'The specified character does not exist.',
            'start_date.date' => 'The start date must be a valid date.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after' => 'The end date must be after the start date.',
            'reason.string' => 'The reason must be a string.',
        ];
    }

    /**
     * Resolve the Character model from the validated character ID.
     */
    public function character(): ?Character
    {
        return $this->has('character') ? Character::find((int) $this->input('character')) : null;
    }
}
