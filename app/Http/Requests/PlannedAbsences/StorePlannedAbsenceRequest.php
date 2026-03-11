<?php

namespace App\Http\Requests\PlannedAbsences;

use App\Models\PlannedAbsence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlannedAbsenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', PlannedAbsence::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $startDateRules = ['required', 'date'];
        if (! $this->user()?->can('createBackdated', PlannedAbsence::class)) {
            $startDateRules[] = 'after_or_equal:today';
        }

        return [
            'character' => is_numeric($this->input('character'))
                ? ['required', 'integer', 'min:1', Rule::exists('characters', 'id')]
                : ['required', 'string', 'max:11', 'regex:/^[^\d\s]+$/u'],
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
            'character.required' => 'A character is required.',
            'character.integer' => 'The character ID must be an integer.',
            'character.min' => 'The character ID must be at least 1.',
            'character.exists' => 'The specified character does not exist.',
            'character.max' => 'Character name must be less than 12 characters.',
            'character.regex' => 'Character name must not contain spaces or numbers.',
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
