<?php

namespace App\Http\Integrations\Blizzard\Data\Item;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class ItemQualityData extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly string $name,
    ) {}
}
