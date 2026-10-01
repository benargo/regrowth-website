<?php

namespace App\Enums;

use App\Contracts\Datasets\SetupStep;
use App\Enums\Concerns\IsSetupStep;

enum GameVersionSetupStep: string implements SetupStep
{
    use IsSetupStep;

    case RACES_AND_CLASSES = 'races-and-classes';
    case PHASES = 'phases';
    case GUILD_RANKS = 'guild-ranks';

    public function label(): string
    {
        return match ($this) {
            self::RACES_AND_CLASSES => 'Races and classes',
            self::PHASES => 'Phases and raids',
            self::GUILD_RANKS => 'Guild ranks',
        };
    }
}
