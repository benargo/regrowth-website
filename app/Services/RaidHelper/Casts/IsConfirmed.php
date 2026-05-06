<?php

namespace App\Services\RaidHelper\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class IsConfirmed implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): bool
    {
        return $value === 'confirmed';
    }
}
