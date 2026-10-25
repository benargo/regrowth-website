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
     * Cast a Warcraft Logs epoch-millisecond timestamp to Carbon in the app timezone.
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->setTimezone(config('app.timezone'));
        }

        return Carbon::createFromTimestampMs($value)->setTimezone(config('app.timezone'));
    }
}
