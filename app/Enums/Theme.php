<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelectOptions;

enum Theme: string
{
    use HasSelectOptions;

    case CLASSIC = 'classic';
    case FOREVER = 'forever';

    /**
     * The site-wide default, used for any route that does not declare a theme.
     */
    public static function default(): self
    {
        return self::from(config('app.theme') ?? self::CLASSIC->value);
    }
}
