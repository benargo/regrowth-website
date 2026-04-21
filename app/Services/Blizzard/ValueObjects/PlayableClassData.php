<?php

namespace App\Services\Blizzard\ValueObjects;

use Spatie\LaravelData\Data;

class PlayableClassData extends Data
{
    /**
     * @param  array{male: string, female: string}  $genderName
     * @param  array{key: array{href: string}, name: string, id: int}  $powerType
     * @param  array{key: array{href: string}, id: int}  $media
     * @param  array{href: string}  $pvpTalentSlots
     * @param  array<int, array{key: array{href: string}, name: string, id: int}>  $playableRaces
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $genderName = [],
        public readonly array $powerType = [],
        public readonly array $media = [],
        public readonly array $pvpTalentSlots = [],
        public readonly array $playableRaces = [],
    ) {}
}
