<?php

namespace App\Http\Requests;

use App\Enums\PlannedAbsenceReason;
use App\Models\PlannedAbsence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A member changing the dates or reason of their own absence. The character
 * and the person it is for are fixed.
 */
class UpdateAccountPlannedAbsenceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PlannedAbsence|null $plannedAbsence */
        $plannedAbsence = $this->route('plannedAbsence');
        $effectiveStartDate = $this->input('start_date') ?? $plannedAbsence?->start_date?->format('Y-m-d');

        $startDateRules = ['sometimes', 'date'];
        if (! $this->user()?->can('createBackdated', PlannedAbsence::class)) {
            $startDateRules[] = 'after_or_equal:today';
        }

        return [
            'character' => ['prohibited'],
            'user' => ['prohibited'],
            'start_date' => $startDateRules,
            'end_date' => ['sometimes', 'nullable', 'date', ...($effectiveStartDate ? ['after:'.$effectiveStartDate] : [])],
            'reason' => ['sometimes', Rule::enum(PlannedAbsenceReason::class)],
            'notes' => [
                'nullable',
                'string',
                Rule::requiredIf(fn (): bool => $this->notesAreRequired($plannedAbsence)),
            ],
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
            'start_date.date' => 'The start date must be a valid date.',
            'start_date.after_or_equal' => 'The start date must not be in the past.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after' => 'The end date must be after the start date.',
            'reason.enum' => 'Choose one of the listed reasons.',
            'notes.required' => 'Add a note when the reason is Other.',
            'notes.string' => 'The notes must be text.',
        ];
    }

    /**
     * Notes are required for an "Other" absence. On a partial update the
     * stored reason and notes stand in for whatever the request leaves out.
     */
    private function notesAreRequired(?PlannedAbsence $plannedAbsence): bool
    {
        $reason = $this->enum('reason', PlannedAbsenceReason::class) ?? $plannedAbsence?->reason?->key;

        if ($reason !== PlannedAbsenceReason::Other) {
            return false;
        }

        return $this->has('notes') || blank($plannedAbsence?->notes);
    }
}
