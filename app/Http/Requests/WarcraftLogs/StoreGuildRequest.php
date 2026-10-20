<?php

namespace App\Http\Requests\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuildRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1', Rule::unique(Guild::class, 'id')],
            'namespace' => ['required', Rule::enum(WarcraftLogsNamespace::class)],
            'logs_url' => ['nullable', 'url:https', 'max:255'],
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
            'id.required' => 'Enter the Warcraft Logs guild ID.',
            'id.unique' => 'This Warcraft Logs guild has already been added.',
            'namespace.required' => 'Choose which Warcraft Logs site the guild is on.',
            'logs_url.url' => "Enter the full https:// address of the guild's Warcraft Logs page.",
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
            'id' => 'Warcraft Logs guild ID',
            'namespace' => 'Warcraft Logs site',
            'logs_url' => 'Warcraft Logs guild page',
        ];
    }
}
