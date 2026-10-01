<?php

namespace App\Http\Integrations\Blizzard;

use InvalidArgumentException;

enum BlizzardNamespace: string
{
    case ANNIVERSARY = 'anniversary';
    case CLASSIC = 'classic';
    case ERA = 'era';
    // case FOREVER = 'forever';
    case RETAIL = 'retail';

    /** @var list<string> */
    private const CATEGORIES = [
        'dynamic',
        'profile',
        'static',
    ];

    /**
     * Resolve the namespace configured as the application-wide fallback.
     *
     * @throws InvalidArgumentException when the configured value matches no case.
     */
    public static function default(): self
    {
        $configured = config('services.blizzard.namespace');

        return self::tryFrom($configured ?? '') ?? throw new InvalidArgumentException(sprintf(
            'Unknown Blizzard namespace: "%s". Expected one of: %s',
            $configured,
            implode(', ', array_column(self::cases(), 'value')),
        ));
    }

    /**
     * Build the Battlenet-Namespace header value for the given category.
     *
     * @throws InvalidArgumentException when the category is not one of CATEGORIES.
     */
    public function value(?string $category = null, ?Region $region = null): string
    {
        if (! $category) {
            return $this->value;
        }

        if (! in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException("Invalid category: $category");
        }

        if (! $region) {
            $region = Region::from(config('services.blizzard.region') ?? 'eu');
        }

        return match ($this) {
            self::ANNIVERSARY => "$category-classicann-{$region->value}",
            self::CLASSIC => "$category-classic-{$region->value}",
            self::ERA => "$category-classic1x-{$region->value}",
            // self::FOREVER => "$category-forever-{$region->value}",
            self::RETAIL => "$category-{$region->value}",
        };
    }

    public function requiresRealm(): bool
    {
        return match ($this) {
            self::ANNIVERSARY, self::CLASSIC, self::ERA, self::RETAIL => true,
            // self::FOREVER => false,
        };
    }

    public function forDynamicRequests(?Region $region = null): string
    {
        return $this->value('dynamic', $region);
    }

    public function forProfileRequests(?Region $region = null): string
    {
        return $this->value('profile', $region);
    }

    public function forStaticRequests(?Region $region = null): string
    {
        return $this->value('static', $region);
    }

    /**
     * Map this namespace to its Wowhead URL path segment.
     *
     * Wowhead hosts retail content at the site root (no segment), Classic Era
     * under `/classic`, and both Anniversary and TBC Classic under `/tbc` —
     * Wowhead has never split Anniversary into its own segment, so it shares
     * the `/tbc` path with TBC Classic.
     */
    public function expansionUrlSegment(): string
    {
        return match ($this) {
            self::RETAIL => '',
            self::ERA => 'classic',
            self::ANNIVERSARY, self::CLASSIC => 'tbc',
        };
    }
}
