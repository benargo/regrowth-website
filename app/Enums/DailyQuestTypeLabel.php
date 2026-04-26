<?php

namespace App\Enums;

enum DailyQuestTypeLabel: string
{
    case CookingQuest = 'Cooking';
    case FishingQuest = 'Fishing';
    case DungeonQuest = 'Normal dungeon';
    case HeroicQuest = 'Heroic dungeon';
    case PvpQuest = 'PvP battleground';

    /**
     * Returns a map of relation name => label for each quest type.
     *
     * @return array<string, string>
     */
    public static function relationNames(): array
    {
        return [
            'cookingQuest' => self::CookingQuest->value,
            'fishingQuest' => self::FishingQuest->value,
            'dungeonQuest' => self::DungeonQuest->value,
            'heroicQuest' => self::HeroicQuest->value,
            'pvpQuest' => self::PvpQuest->value,
        ];
    }
}
