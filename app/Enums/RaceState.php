<?php

namespace App\Enums;

use App\Contracts\Race\RaceToSixty;
use Carbon\CarbonInterface;

enum RaceState: string implements RaceToSixty
{
    case Running = 'running';
    case Closing = 'closing';
    case Finished = 'finished';

    /**
     * Get the race state given when the bronze placement was reached.
     */
    public static function fromBronzeReachedAt(?CarbonInterface $reachedAt): self
    {
        if ($reachedAt === null) {
            return self::Running;
        }

        return $reachedAt->copy()->addHours(self::HONOURABLE_MENTION_HOURS)->lessThanOrEqualTo(now())
            ? self::Finished
            : self::Closing;
    }
}
