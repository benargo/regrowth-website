<?php

namespace App\Http\Requests;

use App\Models\PermissionGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderPermissionGroupsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permission_group_ids' => ['present', 'array'],
            'permission_group_ids.*' => ['uuid', 'distinct'],
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

                $submitted = $this->input('permission_group_ids');
                $existing = PermissionGroup::pluck('id')->all();
                sort($submitted);
                sort($existing);

                if ($submitted !== $existing) {
                    $validator->errors()->add(
                        'permission_group_ids',
                        'The group list is out of date. Reload the page and try again.',
                    );
                }
            },
        ];
    }
}
