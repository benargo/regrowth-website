<?php

namespace App\Http\Integrations\Blizzard\Data\Casts;

use Illuminate\Support\Uri;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class UriCast implements Cast
{
    /**
     * Cast an inbound URL string to an {@see Uri} value object. An existing
     * Uri is returned unchanged so the cast is idempotent.
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): Uri
    {
        return $value instanceof Uri ? $value : Uri::of((string) $value);
    }
}
