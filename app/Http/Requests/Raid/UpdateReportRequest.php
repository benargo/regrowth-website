<?php

namespace App\Http\Requests\Raid;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('report'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Links (optional)
            'links' => ['sometimes', 'array'],
            'links.action' => ['required_with:links', 'string', Rule::in(['create', 'delete'])],
            'links.link_ids' => [Rule::when($this->input('links.action') === 'create', ['required', 'array', 'min:1'], ['nullable', 'array'])],
            'links.link_ids.*' => ['required', 'string', 'exists:raid_reports,id', Rule::notIn([$this->route('report')->getKey()])],

            // Loot councillors (optional)
            'loot_councillors' => ['sometimes', 'array'],
            'loot_councillors.action' => ['required_with:loot_councillors', 'string', Rule::in(['create', 'delete'])],
            'loot_councillors.character_ids' => ['required_with:loot_councillors', 'array', 'min:1'],
            'loot_councillors.character_ids.*' => [
                Rule::when(
                    $this->input('loot_councillors.action') === 'create',
                    ['required', 'integer', Rule::exists('characters', 'id')->where('is_loot_councillor', true)],
                    ['required', 'integer', 'exists:characters,id']
                ),
            ],
        ];
    }
}
