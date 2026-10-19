<?php

namespace App\Enums;

use Illuminate\Http\Request;

/**
 * The member sections that share raid and boss pages.
 */
enum SiteSection: string
{
    case Raiding = 'raiding';
    case Loot = 'loot';

    /**
     * The section named by the current route's `section` default.
     */
    public static function fromRoute(Request $request): self
    {
        $section = $request->route('section');

        return $section instanceof self ? $section : self::from($section);
    }
}
