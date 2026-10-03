<?php

namespace App\Http\Integrations\WarcraftLogs\Data\Casts;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final class MillisecondTimestampCast implements Cast
{
    /**
     * Cast a Warcraft Logs epoch-millisecond timestamp to Carbon. An existing
     * Carbon instance is returned as a Carbon so the cast is idempotent.
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        return Carbon::createFromTimestampMs($value);
    }
}
