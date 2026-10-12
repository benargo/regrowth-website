<?php

namespace App\Http\Requests;

use App\Enums\PlannedAbsenceReason;
use App\Models\PlannedAbsence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlannedAbsenceRequest extends FormRequest
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
            'game_version' => [
                'required_with:character',
                'integer',
                Rule::exists('game_versions', 'id')->where(fn (Builder $query) => $query->where('release_date', '<=', now())),
            ],
            'character' => [
                'sometimes',
                'integer',
                'min:1',
                Rule::exists('characters', 'id')
                    ->where('game_version_id', $this->integer('game_version'))
                    ->where('is_main', true),
            ],
            'user' => ['sometimes', 'nullable', 'string'],
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
            'game_version.required_with' => 'A game version is required.',
            'game_version.integer' => 'The game version must be an ID.',
            'game_version.exists' => "That game version isn't available.",
            'character.integer' => 'The character ID must be an integer.',
            'character.min' => 'The character ID must be at least 1.',
            'character.exists' => 'Choose a main character.',
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
