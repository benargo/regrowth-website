<?php

namespace App\Contracts\Http\Integrations\Blizzard;

/**
 * Skips characters below the level the Blizzard profile API returns data for.
 */
interface RequiresMinimumCharacterLevel
{
    /**
     * The minimum character level the Blizzard profile API returns data for.
     */
    public const int MIN_LEVEL = 10;
}
