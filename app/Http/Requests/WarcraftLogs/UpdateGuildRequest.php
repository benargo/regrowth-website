<?php

namespace App\Http\Requests\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuildRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'namespace' => ['required', Rule::enum(WarcraftLogsNamespace::class)],
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
            'namespace.required' => 'Choose which Warcraft Logs site the guild is on.',
        ];
    }

    /**
     * Get the human-readable field names used in default messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'namespace' => 'Warcraft Logs site',
        ];
    }
}
