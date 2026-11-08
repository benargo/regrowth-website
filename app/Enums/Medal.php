<?php

namespace App\Enums;

enum Medal: string
{
    case Gold = 'gold';
    case Silver = 'silver';
    case Bronze = 'bronze';

    /**
     * Get the medal awarded for a finishing position, if any.
     */
    public static function fromPosition(int $position): ?self
    {
        return match ($position) {
            1 => self::Gold,
            2 => self::Silver,
            3 => self::Bronze,
            default => null,
        };
    }

    /**
     * Get the embed colour of the medal as an integer.
     */
    public function color(): int
    {
        return match ($this) {
            self::Gold => 0xE3B341,
            self::Silver => 0xC3CBD0,
            self::Bronze => 0xD08A4F,
        };
    }

    /**
     * Get the ordinal word for the medal's position.
     */
    public function ordinal(): string
    {
        return match ($this) {
            self::Gold => 'first',
            self::Silver => 'second',
            self::Bronze => 'third',
        };
    }
}
