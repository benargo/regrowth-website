<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class TogglePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('access-dashboard');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'discord_role_id' => ['required', 'string', 'exists:discord_roles,id'],
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
            'enabled' => ['required', 'boolean'],
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
            'discord_role_id.required' => 'A Discord role is required.',
            'discord_role_id.exists' => 'The selected Discord role does not exist.',
            'permission_id.required' => 'A permission is required.',
            'permission_id.exists' => 'The selected permission does not exist.',
            'enabled.required' => 'The enabled field is required.',
            'enabled.boolean' => 'The enabled field must be true or false.',
        ];
    }
}
