<?php

namespace App\Http\Integrations\Blizzard\Data\Characters;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class CharacterStatusData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly bool $isValid,
    ) {}
}
