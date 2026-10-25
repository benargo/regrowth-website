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
     * The section's feature flags, shared with its raid and boss pages as the `features` prop.
     *
     * @return array{itemComments: bool, strategy: bool}
     */
    public function features(): array
    {
        return [
            'itemComments' => $this->showsItemComments(),
            'strategy' => $this->showsStrategy(),
        ];
    }

    /**
     * Whether the section shows boss strategies.
     */
    public function showsStrategy(): bool
    {
        return match ($this) {
            self::Raiding => true,
            self::Loot => false,
        };
    }

    /**
     * Whether the section shows item comment counts.
     */
    public function showsItemComments(): bool
    {
        return match ($this) {
            self::Raiding => false,
            self::Loot => true,
        };
    }
}
