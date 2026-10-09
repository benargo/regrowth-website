<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelectOptions;

enum Faction: string
{
    use HasSelectOptions;

    case ALLIANCE = 'Alliance';
    case HORDE = 'Horde';
    case NEUTRAL = 'Neutral';
}
