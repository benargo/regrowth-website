<?php

namespace App\Enums;

enum CharacterRole: string
{
    case tank = 'Tank';
    case healer = 'Healer';
    case damage = 'DPS';

    /**
     * Get the URL for the icon representing this role.
     */
    public function icon(): string
    {
        $filename = match ($this) {
            self::tank => 'role_tank.webp',
            self::healer => 'role_healer.webp',
            self::damage => 'role_damage.webp',
        };

        return asset("images/{$filename}");
    }
}
