<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventGroupRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'boss_id' => ['sometimes', 'nullable', 'integer', 'exists:bosses,id'],
        ];
    }
}
