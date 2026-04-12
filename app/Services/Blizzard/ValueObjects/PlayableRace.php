<?php

namespace App\Services\Blizzard\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

readonly class PlayableRace implements Arrayable
{
    /**
     * @param  array{male: string, female: string}  $genderName
     * @param  array{type: string, name: string}  $faction
     * @param  array<int, array{key: array{href: string}, name: string, id: int}>  $playableClasses
     * @param  array<int, array{key: array{href: string}, name: string, id: int}>  $racialSpells
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $genderName,
        public array $faction,
        public bool $isSelectable,
        public bool $isAlliedRace,
        public array $playableClasses,
        public array $racialSpells,
    ) {}

    /**
     * Build a PlayableRace from a raw Blizzard /data/wow/playable-race/{id} response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: (int) Arr::get($data, 'id'),
            name: (string) Arr::get($data, 'name'),
            genderName: Arr::get($data, 'gender_name', []),
            faction: Arr::get($data, 'faction', []),
            isSelectable: (bool) Arr::get($data, 'is_selectable', false),
            isAlliedRace: (bool) Arr::get($data, 'is_allied_race', false),
            playableClasses: Arr::get($data, 'playable_classes', []),
            racialSpells: Arr::get($data, 'racial_spells', []),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     gender_name: array{male: string, female: string},
     *     faction: array{type: string, name: string},
     *     is_selectable: bool,
     *     is_allied_race: bool,
     *     playable_classes: array<int, array{key: array{href: string}, name: string, id: int}>,
     *     racial_spells: array<int, array{key: array{href: string}, name: string, id: int}>
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'gender_name' => $this->genderName,
            'faction' => $this->faction,
            'is_selectable' => $this->isSelectable,
            'is_allied_race' => $this->isAlliedRace,
            'playable_classes' => $this->playableClasses,
            'racial_spells' => $this->racialSpells,
        ];
    }
}
