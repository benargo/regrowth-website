<?php

namespace App\Services\Blizzard\ValueObjects;

use Spatie\LaravelData\Data;

class PlayableRaceData extends Data
{
    /**
     * @param  array{male: string, female: string}  $genderName
     * @param  array{type: string, name: string}  $faction
     * @param  array<int, array{key: array{href: string}, name: string, id: int}>  $playableClasses
     * @param  array<int, array{key: array{href: string}, name: string, id: int}>  $racialSpells
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $genderName = [],
        public readonly array $faction = [],
        public readonly bool $isSelectable = false,
        public readonly bool $isAlliedRace = false,
        public readonly array $playableClasses = [],
        public readonly array $racialSpells = [],
    ) {}
}
