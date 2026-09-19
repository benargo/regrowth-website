<?php

namespace App\Http\Integrations\Blizzard;

use InvalidArgumentException;

enum BlizzardNamespace: string
{
    case anniversary = 'anniversary';
    case classic = 'classic';
    case era = 'era';
    // case forever = 'forever';
    case retail = 'retail';

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
        $configured = config('services.blizzard.namespace') ?? 'anniversary';

        return self::tryFrom($configured) ?? throw new InvalidArgumentException(sprintf(
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
            self::anniversary => "$category-classicann-{$region->value}",
            self::classic => "$category-classic-{$region->value}",
            self::era => "$category-classic1x-{$region->value}",
            // self::forever => "$category-forever-{$region->value}",
            self::retail => "$category-{$region->value}",
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
}
