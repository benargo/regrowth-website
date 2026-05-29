<?php

namespace App\Http\Integrations\Blizzard\Data\PlayableRace;

use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class PlayableRaceData extends Data
{
    /**
     * @param  array{male: string, female: string}  $genderName
     * @param  array{type: string, name: string}  $faction
     * @param  array<int, LinkData>  $playableClasses
     * @param  array<int, LinkData>  $racialSpells
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $genderName,
        public readonly array $faction,
        public readonly bool $isSelectable,
        public readonly bool $isAlliedRace,
        #[DataCollectionOf(LinkData::class)]
        public readonly array $playableClasses,
        #[DataCollectionOf(LinkData::class)]
        public readonly array $racialSpells,
    ) {}
}
