<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handled by route middleware: can:update,event
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'boss_id' => ['sometimes', 'nullable', 'integer', 'exists:bosses,id'],
            'group_id' => ['sometimes', 'nullable', 'integer', 'exists:event_assignment_groups,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'left_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'left_value' => ['sometimes', 'nullable', 'string'],
            'right_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'right_value' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
