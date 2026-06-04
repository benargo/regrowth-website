<?php

namespace App\Http\Requests;

use App\Enums\DailyQuestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyQuestsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'cooking_quest_id' => [
                'nullable',
                'integer',
                Rule::exists('daily_quests', 'id')->where('type', DailyQuestType::Cooking->value),
            ],
            'fishing_quest_id' => [
                'nullable',
                'integer',
                Rule::exists('daily_quests', 'id')->where('type', DailyQuestType::Fishing->value),
            ],
            'dungeon_quest_id' => [
                'nullable',
                'integer',
                Rule::exists('daily_quests', 'id')->where('type', DailyQuestType::Dungeon->value),
            ],
            'heroic_quest_id' => [
                'nullable',
                'integer',
                Rule::exists('daily_quests', 'id')->where('type', DailyQuestType::Heroic->value),
            ],
            'pvp_quest_id' => [
                'nullable',
                'integer',
                Rule::exists('daily_quests', 'id')->where('type', DailyQuestType::PvP->value),
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
