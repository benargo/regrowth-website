<?php

namespace App\Http\Integrations\Blizzard\Data\Characters;

use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
class CharacterProfileData extends Data
{
    /**
     * @param  array{type: string, name: string}  $gender
     * @param  array{type: string, name: string}  $faction
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $gender,
        public readonly array $faction,
        public readonly LinkData $race,
        public readonly LinkData $characterClass,
        public readonly LinkData $realm,
        public readonly int $level,
        public readonly int $lastLoginTimestamp,
        public readonly int $averageItemLevel,
        public readonly int $equippedItemLevel,
        public readonly Optional|LinkData $activeSpec,
        public readonly Optional|LinkData $guild,
        public readonly Optional|int $experience,
        public readonly Optional|int $achievementPoints,
    ) {}
}
