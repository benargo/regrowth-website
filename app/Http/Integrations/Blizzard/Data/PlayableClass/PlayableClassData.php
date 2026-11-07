<?php

namespace App\Http\Integrations\Blizzard\Data\PlayableClass;

use App\Http\Integrations\Blizzard\Data\Shared\HrefData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
class PlayableClassData extends Data
{
    /**
     * @param  array{male: string, female: string}|Optional  $genderName
     * @param  array<int, LinkData>|Optional  $playableRaces
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array|Optional $genderName,
        public readonly LinkData|Optional $powerType,
        public readonly LinkData|Optional $media,
        public readonly HrefData|Optional $pvpTalentSlots,
        #[DataCollectionOf(LinkData::class)]
        public readonly array|Optional $playableRaces,
    ) {}
}
