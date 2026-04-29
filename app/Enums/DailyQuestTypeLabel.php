<?php

namespace App\Enums;

use Illuminate\Support\Arr;

enum DailyQuestTypeLabel: string
{
    case Cooking = 'Cooking';
    case Fishing = 'Fishing';
    case Dungeon = 'Normal dungeon';
    case Heroic = 'Heroic dungeon';
    case PvP = 'PvP battleground';

    public static function map(): array
    {
        return Arr::mapWithKeys(self::cases(), fn (self $case) => [$case->name => $case->value]);
    }
}
