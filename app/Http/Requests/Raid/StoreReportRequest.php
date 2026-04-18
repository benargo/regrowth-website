<?php

namespace App\Http\Requests\Raid;

use App\Models\Raids\Report;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Report::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'guild_tag_id' => ['required', 'integer', 'exists:wcl_guild_tags,id'],
            'zone_id' => ['required', 'integer', 'exists:wcl_zones,id'],
            'character_ids' => ['nullable', 'array'],
            'character_ids.*' => ['required', 'exists:characters,id'],
            'loot_councillor_ids' => ['nullable', 'array'],
            'loot_councillor_ids.*' => ['required', 'integer', Rule::exists('characters', 'id')->where('is_loot_councillor', true)],
            'linked_report_ids' => ['nullable', 'array'],
            'linked_report_ids.*' => ['required', 'string', 'exists:raid_reports,id'],
        ];
    }
}
