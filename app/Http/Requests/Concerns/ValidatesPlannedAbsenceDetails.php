<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PlannedAbsenceReason;
use App\Models\PlannedAbsence;
use Illuminate\Validation\Rule;

/**
 * For form requests that create or change a planned absence: the date,
 * reason and notes rules shared by the officer and self-service forms.
 */
trait ValidatesPlannedAbsenceDetails
{
    /**
     * Rules for the start date, end date, reason and notes of a new absence.
     *
     * @return array<string, array<mixed>>
     */
    protected function createAbsenceDetailRules(): array
    {
        return [
            'start_date' => ['required', ...$this->startDateRules()],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'reason' => ['required', Rule::enum(PlannedAbsenceReason::class)],
            'notes' => $this->notesRules(null),
        ];
    }

    /**
     * Rules for a partial change to an absence's dates, reason and notes. The
     * stored values stand in for whatever the request leaves out.
     *
     * @return array<string, array<mixed>>
     */
    protected function updateAbsenceDetailRules(?PlannedAbsence $plannedAbsence): array
    {
        $effectiveStartDate = $this->input('start_date') ?? $plannedAbsence?->start_date?->format('Y-m-d');

        return [
            'start_date' => ['sometimes', ...$this->startDateRules()],
            'end_date' => ['sometimes', 'nullable', 'date', ...($effectiveStartDate ? ['after:'.$effectiveStartDate] : [])],
            'reason' => ['sometimes', Rule::enum(PlannedAbsenceReason::class)],
            'notes' => $this->notesRules($plannedAbsence),
        ];
    }

    /**
     * Messages for the rules from createAbsenceDetailRules() and
     * updateAbsenceDetailRules().
     *
     * @return array<string, string>
     */
    protected function absenceDetailMessages(): array
    {
        return [
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

    /**
     * @return array<int, string>
     */
    private function startDateRules(): array
    {
        if ($this->user()?->can('createBackdated', PlannedAbsence::class)) {
            return ['date'];
        }

        return ['date', 'after_or_equal:today'];
    }

    /**
     * @return array<int, mixed>
     */
    private function notesRules(?PlannedAbsence $plannedAbsence): array
    {
        return [
            'nullable',
            'string',
            Rule::requiredIf(fn (): bool => $this->notesAreRequired($plannedAbsence)),
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
