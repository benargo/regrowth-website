<?php

namespace App\Http\Integrations\Blizzard\Data\Characters;

use App\Http\Integrations\Blizzard\Data\Shared\HrefData;
use App\Http\Integrations\Blizzard\Data\Shared\RealmLinkData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
final class CharacterLinkData extends Data
{
    public function __construct(
        public readonly HrefData $key,
        public readonly string $name,
        public readonly int $id,
        public readonly Optional|string $surname,
        public readonly Optional|RealmLinkData $realm,
    ) {}
}
