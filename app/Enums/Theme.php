<?php

namespace App\Enums;

enum Theme: string
{
    case CLASSIC = 'classic';
    case FOREVER = 'forever';

    /**
     * The site-wide default, used for any route that does not declare a theme.
     */
    public static function default(): self
    {
        return self::from(config('app.theme') ?? self::CLASSIC->value);
    }

    /**
     * Get the CSS class for the banner background for this theme.
     */
    public function bannerCssClass(string $prefix = 'bg'): string
    {
        return match ($this) {
            self::CLASSIC => "$prefix-raid-black-temple",
            self::FOREVER => "$prefix-camelot",
        };
    }
}
