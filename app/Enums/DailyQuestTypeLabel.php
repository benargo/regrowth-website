<?php

namespace App\Enums;

enum DailyQuestTypeLabel: string
{
    case Cooking = 'Cooking';
    case Fishing = 'Fishing';
    case Dungeon = 'Normal dungeon';
    case Heroic = 'Heroic dungeon';
    case PvP = 'PvP battleground';
}
