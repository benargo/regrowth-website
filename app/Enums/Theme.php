<?php

namespace App\Enums;

use Illuminate\Support\Facades\Vite;

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
     * Get the path to the banner image for this theme.
     */
    public function bannerImagePath(): string
    {
        $path = match ($this) {
            self::CLASSIC => 'resources/images/banner_anniversary.webp',
            self::FOREVER => 'resources/images/banner_camelot.webp',
        };

        return Vite::asset($path);
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

    /**
     * Get the path to the icon image for this theme.
     */
    public function iconPath(): string
    {
        $path = match ($this) {
            self::CLASSIC => 'resources/images/icon_tbcclassic.webp',
            self::FOREVER => 'resources/images/icon_camelot.webp',
        };

        return Vite::asset($path);
    }
}
