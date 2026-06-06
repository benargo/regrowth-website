<?php

namespace App\Http\Integrations\Blizzard\Data\PlayableRace;

use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
class PlayableRaceData extends Data
{
    /**
     * @param  array{male: string, female: string}|Optional  $genderName
     * @param  array{type: string, name: string}|Optional  $faction
     * @param  array<int, LinkData>|Optional  $playableClasses
     * @param  array<int, LinkData>|Optional  $racialSpells
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array|Optional $genderName,
        public readonly array|Optional $faction,
        public readonly bool|Optional $isSelectable,
        public readonly bool|Optional $isAlliedRace,
        #[DataCollectionOf(LinkData::class)]
        public readonly array|Optional $playableClasses,
        #[DataCollectionOf(LinkData::class)]
        public readonly array|Optional $racialSpells,
    ) {}
}
