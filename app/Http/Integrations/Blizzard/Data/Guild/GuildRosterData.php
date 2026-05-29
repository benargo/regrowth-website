<?php

namespace App\Http\Integrations\Blizzard\Data\Guild;

use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class GuildRosterData extends Data
{
    /**
     * @param  array<int, GuildRosterMemberData>  $members
     */
    public function __construct(
        public readonly LinkData $guild,
        #[DataCollectionOf(GuildRosterMemberData::class)]
        public readonly array $members,
    ) {}
}
