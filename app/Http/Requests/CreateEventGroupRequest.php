<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEventGroupRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'boss_id' => ['nullable', 'integer', 'exists:bosses,id'],
        ];
    }
}
