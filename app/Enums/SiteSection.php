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
        return self::from($request->route('section'));
    }

    /**
     * Whether the section shows boss strategies. Pairs with the JS `features.strategy` flag.
     */
    public function showsStrategy(): bool
    {
        return match ($this) {
            self::Raiding => true,
            self::Loot => false,
        };
    }

    /**
     * Whether the section shows item comment counts. Pairs with the JS `features.itemComments` flag.
     */
    public function showsItemComments(): bool
    {
        return match ($this) {
            self::Raiding => false,
            self::Loot => true,
        };
    }
}
