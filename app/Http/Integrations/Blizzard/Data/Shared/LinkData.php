<?php

namespace App\Http\Integrations\Blizzard\Data\Shared;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
class LinkData extends Data
{
    public function __construct(
        public readonly HrefData $key,
        public readonly Optional|string $name,
        public readonly Optional|int $id,
    ) {}
}
