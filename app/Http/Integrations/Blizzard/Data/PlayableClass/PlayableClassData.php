<?php

namespace App\Http\Integrations\Blizzard\Data\PlayableClass;

use App\Http\Integrations\Blizzard\Data\Shared\HrefData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class PlayableClassData extends Data
{
    /**
     * @param  array{male: string, female: string}  $genderName
     * @param  array<int, LinkData>  $playableRaces
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $genderName,
        public readonly LinkData $powerType,
        public readonly LinkData $media,
        public readonly HrefData $pvpTalentSlots,
        #[DataCollectionOf(LinkData::class)]
        public readonly array $playableRaces,
    ) {}
}
