<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEventAssignmentRequest extends FormRequest
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
            'boss_id' => ['nullable', 'integer', 'exists:bosses,id'],
            'group_id' => ['nullable', 'integer', 'exists:event_assignment_groups,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'left_type' => ['nullable', 'string', 'max:255'],
            'left_value' => ['nullable', 'string'],
            'right_type' => ['nullable', 'string', 'max:255'],
            'right_value' => ['nullable', 'string'],
        ];
    }
}
