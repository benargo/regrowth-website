<?php

namespace App\Enums;

use Illuminate\Support\Arr;

enum AllianceRaces: int
{
    case Human = 1;
    case Dwarf = 3;
    case NightElf = 4;
    case Gnome = 7;
    case Draenei = 11;

    public static function fromId(int $id): ?self
    {
        return Arr::first(self::cases(), fn ($case) => $case->value === $id, null);
    }

    public static function ids(): array
    {
        return array_column(self::cases(), 'value');
    }
}
