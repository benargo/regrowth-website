<?php

namespace App\Http\Integrations\Blizzard\Data\Shared;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class RealmLinkData extends Data
{
    public function __construct(
        public readonly HrefData $key,
        public readonly string $name,
        public readonly int $id,
        public readonly string $slug,
    ) {}
}
