<?php

namespace App\Enums;

use Illuminate\Support\Arr;

enum LootPriorityType: string
{
    case ROLE = 'Role';
    case CLASS_TYPE = 'Class';
    case SPEC = 'Spec';
    case CUSTOM = 'Custom';
    case MEME = 'Meme';

    /**
     * Get an array mapping the enum case names to their values.
     */
    public static function map(): array
    {
        return Arr::mapWithKeys(self::cases(), fn (self $case) => [$case->name => $case->value]);
    }

    /**
     * Get the display order for this type relative to the other types.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::ROLE => 0,
            self::CLASS_TYPE => 1,
            self::SPEC => 2,
            self::CUSTOM => 3,
            self::MEME => 4,
        };
    }
}
