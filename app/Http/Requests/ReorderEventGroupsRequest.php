<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderEventGroupsRequest extends FormRequest
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
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:event_assignment_groups,id'],
        ];
    }
}
