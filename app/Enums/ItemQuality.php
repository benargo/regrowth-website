<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ItemQuality: string
{
    case POOR = 'Poor';
    case COMMON = 'Common';
    case UNCOMMON = 'Uncommon';
    case RARE = 'Rare';
    case EPIC = 'Epic';
    case LEGENDARY = 'Legendary';
    case ARTIFACT = 'Artifact';
    case HEIRLOOM = 'Heirloom';

    /**
     * Get the hex color code associated with this item quality.
     */
    public function colorCode(): int
    {
        return match ($this) {
            self::POOR => 0x9D9D9D,
            self::COMMON => 0xFFFFFF,
            self::UNCOMMON => 0x1EFF00,
            self::RARE => 0x0070DD,
            self::EPIC => 0xA335EE,
            self::LEGENDARY => 0xFF8000,
            self::ARTIFACT => 0xE6CC80,
            self::HEIRLOOM => 0x00CCFF,
        };
    }

    /**
     * Get a CSS class name corresponding to this item quality, which can be used for styling purposes.
     */
    public function cssClass(): string
    {
        return 'item-quality-'.Str::slug($this->name);
    }
}
