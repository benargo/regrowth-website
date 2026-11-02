<?php

namespace App\Http\Requests;

use App\Models\PermissionGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:160'],
            'permission_group_id' => ['nullable', 'uuid', Rule::exists(PermissionGroup::class, 'id')],
        ];
    }
}
