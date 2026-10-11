<?php

namespace App\Http\Requests;

use App\Models\PlannedAbsence;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlannedAbsenceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $startDateRules = ['required', 'date'];
        if (! $this->user()?->can('createBackdated', PlannedAbsence::class)) {
            $startDateRules[] = 'after_or_equal:today';
        }

        return [
            'game_version' => [
                'required',
                'integer',
                Rule::exists('game_versions', 'id')->where(fn (Builder $query) => $query->where('release_date', '<=', now())),
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
            'start_date' => $startDateRules,
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'reason' => ['required', 'string'],
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
            'game_version.exists' => "That game version isn't available.",
            'character.required' => 'A character is required.',
            'character.integer' => 'The character ID must be an integer.',
            'character.min' => 'The character ID must be at least 1.',
            'character.exists' => 'Choose a main character.',
            'start_date.required' => 'A start date is required.',
            'start_date.date' => 'The start date must be a valid date.',
            'start_date.after_or_equal' => 'The start date must not be in the past.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after' => 'The end date must be after the start date.',
            'reason.required' => 'A reason is required.',
            'reason.string' => 'The reason must be a string.',
        ];
    }
}
