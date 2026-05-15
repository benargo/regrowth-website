<?php

namespace App\Enums;

enum AffectType: string
{
    case Curse = 'Curse';
    case Disease = 'Disease';
    case Magic = 'Magic';
    case Poison = 'Poison';
    case Physical = 'Physical';

    /**
     * Get the corresponding Tailwind CSS class for the affect type's color.
     */
    public function color(): string
    {
        return match ($this) {
            self::Curse => 'affect-curse',
            self::Disease => 'affect-disease',
            self::Magic => 'affect-magic',
            self::Poison => 'affect-poison',
            self::Physical => 'affect-physical',
            default => 'gray-700',
        };
    }
}
