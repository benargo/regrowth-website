<?php

namespace App\Http\Requests\Items;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateItemPrioritiesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'priorities' => ['present', 'array'],
            'priorities.*.priority_id' => ['required', 'integer', 'exists:loot_priorities,id'],
            'priorities.*.weight' => ['required', 'integer', 'min:0'],
        ];
    }
}
