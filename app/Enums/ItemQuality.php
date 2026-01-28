<?php

namespace App\Enums;

enum ItemQuality: string
{
    case Poor = '9d9d9d';
    case Common = 'ffffff';
    case Uncommon = '1eff00';
    case Rare = '0070dd';
    case Epic = 'a335ee';
    case Legendary = 'ff8000';
    case Artifact = 'e6cc80';
    case Heirloom = '00ccff';

    public function cssClass(): string
    {
        return 'item-quality-'.strtolower($this->name);
    }

    /**
     * Find the ItemQuality by its name (case-insensitive).
     */
    public static function fromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if (strcasecmp($case->name, $name) === 0) {
                return $case;
            }
        }

        return null;
    }
}
