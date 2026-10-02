<?php

namespace App\Http\Integrations\Blizzard\Data\Guild;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class GuildData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $memberCount,
    ) {}
}
