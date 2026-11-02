<?php

namespace App\Http\Requests;

use App\Models\DiscordRole;
use App\Models\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePermissionAccessRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'discord_role_id' => ['required', 'string', Rule::exists(DiscordRole::class, 'id')->where('grants_permissions', true)],
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'distinct'],
            'enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'discord_role_id.exists' => 'The selected Discord role does not grant permissions.',
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = $this->input('permission_ids');

                if (Permission::whereKey($ids)->count() !== count($ids)) {
                    $validator->errors()->add(
                        'permission_ids',
                        'One or more of the selected permissions do not exist.',
                    );
                }
            },
        ];
    }
}
