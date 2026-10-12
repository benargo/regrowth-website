<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPlannedAbsenceDetails;
use App\Models\PlannedAbsence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A member changing the dates or reason of their own absence. The character
 * and the person it is for are fixed.
 */
class UpdateAccountPlannedAbsenceRequest extends FormRequest
{
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
            'character' => ['prohibited'],
            'user' => ['prohibited'],
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
            'character.prohibited' => "An absence's character can't be changed.",
            'user.prohibited' => 'You can only log absences for yourself here.',
            ...$this->absenceDetailMessages(),
        ];
    }
}
