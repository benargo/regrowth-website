<?php

namespace App\Services\Blizzard\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

readonly class PlayableClass implements Arrayable
{
    /**
     * @param  array{male: string, female: string}  $genderName
     * @param  array{key: array{href: string}, name: string, id: int}  $powerType
     * @param  array{key: array{href: string}, id: int}  $media
     * @param  array{href: string}  $pvpTalentSlots
     * @param  array<int, array{key: array{href: string}, name: string, id: int}>  $playableRaces
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $genderName,
        public array $powerType,
        public array $media,
        public array $pvpTalentSlots,
        public array $playableRaces,
    ) {}

    /**
     * Build a PlayableClass from a raw Blizzard /data/wow/playable-class/{id} response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: (int) Arr::get($data, 'id'),
            name: (string) Arr::get($data, 'name'),
            genderName: Arr::get($data, 'gender_name', []),
            powerType: Arr::get($data, 'power_type', []),
            media: Arr::get($data, 'media', []),
            pvpTalentSlots: Arr::get($data, 'pvp_talent_slots', []),
            playableRaces: Arr::get($data, 'playable_races', []),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     gender_name: array{male: string, female: string},
     *     power_type: array{key: array{href: string}, name: string, id: int},
     *     media: array{key: array{href: string}, id: int},
     *     pvp_talent_slots: array{href: string},
     *     playable_races: array<int, array{key: array{href: string}, name: string, id: int}>
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'gender_name' => $this->genderName,
            'power_type' => $this->powerType,
            'media' => $this->media,
            'pvp_talent_slots' => $this->pvpTalentSlots,
            'playable_races' => $this->playableRaces,
        ];
    }
}
