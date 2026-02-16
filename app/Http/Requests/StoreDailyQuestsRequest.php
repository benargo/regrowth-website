<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyQuestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-dashboard');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'cooking_quest_id' => [
                'nullable',
                'integer',
                Rule::exists('tbc_daily_quests', 'id')->where('type', 'Cooking'),
            ],
            'fishing_quest_id' => [
                'nullable',
                'integer',
                Rule::exists('tbc_daily_quests', 'id')->where('type', 'Fishing'),
            ],
            'dungeon_quest_id' => [
                'nullable',
                'integer',
                Rule::exists('tbc_daily_quests', 'id')
                    ->where('type', 'Dungeon')
                    ->where('mode', 'Normal'),
            ],
            'heroic_quest_id' => [
                'nullable',
                'integer',
                Rule::exists('tbc_daily_quests', 'id')
                    ->where('type', 'Dungeon')
                    ->where('mode', 'Heroic'),
            ],
            'pvp_quest_id' => [
                'nullable',
                'integer',
                Rule::exists('tbc_daily_quests', 'id')->where('type', 'PvP'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.exists' => 'The selected quest is invalid for this category.',
            '*.integer' => 'The quest ID must be a valid number.',
        ];
    }
}
