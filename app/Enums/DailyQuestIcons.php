<?php

namespace App\Enums;

enum DailyQuestIcons: string
{
    case Cooking = 'inv_misc_food_15';
    case Fishing = 'trade_fishing';
    case Dungeon = 'inv_qiraj_jewelencased';
    case HeroicDungeon = 'spell_holy_championsbond';
    case PvP = 'inv_bannerpvp_02';
    case Default = 'inv_misc_questionmark';
}
