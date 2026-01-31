<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuildRankPositionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isOfficer();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ranks' => ['required', 'array', 'min:1'],
            'ranks.*.id' => ['required', 'integer', 'exists:guild_ranks,id'],
            'ranks.*.position' => ['required', 'integer', 'min:0'],
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
            'ranks.required' => 'At least one rank position is required.',
            'ranks.*.id.exists' => 'One or more guild ranks could not be found.',
            'ranks.*.position.min' => 'Position must be 0 or greater.',
        ];
    }
}
