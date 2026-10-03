<?php

namespace App\Http\Integrations\WarcraftLogs\Data\RateLimit;

use Spatie\LaravelData\Data;

/**
 * The API key's points budget for the current hour, from the `rateLimitData` query.
 */
final class RateLimitData extends Data
{
    public function __construct(
        public readonly int $limitPerHour,
        public readonly float $pointsSpentThisHour,
        public readonly int $pointsResetIn,
    ) {}
}
