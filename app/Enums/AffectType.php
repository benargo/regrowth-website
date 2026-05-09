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
            self::Curse => 'bg-affect-curse',
            self::Disease => 'bg-affect-disease',
            self::Magic => 'bg-affect-magic',
            self::Poison => 'bg-affect-poison',
            self::Physical => 'bg-affect-physical',
        };
    }
}
