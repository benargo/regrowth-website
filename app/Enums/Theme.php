<?php

namespace App\Enums;

enum Theme: string
{
    case Classic = 'classic';
    case Forever = 'forever';

    /**
     * The site-wide default, used for any route that does not declare a theme.
     */
    public static function default(): self
    {
        return self::from(config('app.theme') ?? self::Classic->value);
    }
}
