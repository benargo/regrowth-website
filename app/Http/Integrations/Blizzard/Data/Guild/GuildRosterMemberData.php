<?php

namespace App\Http\Integrations\Blizzard\Data\Guild;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class GuildRosterMemberData extends Data
{
    public function __construct(
        public readonly GuildRosterCharacterData $character,
        public readonly int $rank,
    ) {}
}
