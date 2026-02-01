<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePhaseGuildTagsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update-phase', $this->route('phase'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'guild_tag_ids' => ['present', 'array'],
            'guild_tag_ids.*' => ['integer', 'exists:wcl_guild_tags,id'],
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
            'guild_tag_ids.present' => 'The guild tags field is required.',
            'guild_tag_ids.array' => 'The guild tags must be an array.',
            'guild_tag_ids.*.integer' => 'Each guild tag ID must be a number.',
            'guild_tag_ids.*.exists' => 'One or more selected guild tags do not exist.',
        ];
    }
}
