<?php

namespace App\Http\Integrations\Blizzard\Data\Guild;

use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class GuildRosterCharacterData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $level,
        public readonly LinkData $playableClass,
        public readonly LinkData $playableRace,
        public readonly LinkData $realm,
    ) {}
}
