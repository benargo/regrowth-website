<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelectOptions;

/**
 * The CSS background position of a raid's banner: which part stays in view when cropped.
 */
enum BannerPosition: string
{
    use HasSelectOptions;

    case Center = 'center';
    case CenterTop = 'center top';
    case CenterBottom = 'center bottom';

    public function label(): string
    {
        return match ($this) {
            self::Center => 'Middle',
            self::CenterTop => 'Top',
            self::CenterBottom => 'Bottom',
        };
    }
}
