<?php

namespace App\Enums;

use App\Contracts\Datasets\SetupStep;
use App\Enums\Concerns\IsSetupStep;

enum RaidSetupStep: string implements SetupStep
{
    use IsSetupStep;

    case Bosses = 'bosses';

    public function label(): string
    {
        return match ($this) {
            self::Bosses => 'Bosses',
        };
    }
}
