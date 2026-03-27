<?php

namespace App\Http\Requests\Items;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateItemPrioritiesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('item'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'priorities' => ['present', 'array'],
            'priorities.*.priority_id' => ['required', 'integer', 'exists:lootcouncil_priorities,id'],
            'priorities.*.weight' => ['required', 'integer', 'min:0'],
        ];
    }
}
