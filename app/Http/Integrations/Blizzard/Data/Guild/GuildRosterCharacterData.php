<?php

namespace App\Http\Integrations\Blizzard\Data\Guild;

use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
class GuildRosterCharacterData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $level,
        public readonly LinkData $playableClass,
        public readonly LinkData $playableRace,
        public readonly Optional|LinkData $realm,
        public readonly Optional|string $surname = new Optional,
    ) {}

    /**
     * The name and surname separated by a space, or the name alone when there is no surname.
     */
    public function fullName(): string
    {
        return $this->surname instanceof Optional ? $this->name : "{$this->name} {$this->surname}";
    }

    /**
     * The full name when the game version uses surnames, otherwise the name alone.
     */
    public function nameForVersion(bool $usesSurnames): string
    {
        return $usesSurnames ? $this->fullName() : $this->name;
    }

    /**
     * The surname when the game version uses surnames and the character has one, otherwise null.
     */
    public function surnameForVersion(bool $usesSurnames): ?string
    {
        if (! $usesSurnames || $this->surname instanceof Optional) {
            return null;
        }

        return $this->surname;
    }
}
