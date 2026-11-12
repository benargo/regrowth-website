<?php

namespace App\Http\Integrations\Blizzard\Data\Guild;

use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use Illuminate\Support\Str;
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
        $surname = $this->presentSurname();

        return $surname === null ? $this->name : "{$this->name} {$surname}";
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
        if (! $usesSurnames) {
            return null;
        }

        return $this->presentSurname();
    }

    /**
     * The trimmed surname, or null when it is missing or blank.
     */
    private function presentSurname(): ?string
    {
        if ($this->surname instanceof Optional) {
            return null;
        }

        $surname = Str::trim($this->surname);

        return $surname === '' ? null : $surname;
    }
}
