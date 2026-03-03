<?php

namespace App\Services\Blizzard\Data;

use App\Services\Blizzard\PlayableRaceService;
use Illuminate\Support\Arr;

readonly class PlayableRace
{
    public function __construct(
        public ?int $id,
        public string $name,
    ) {}

    /**
     * Build a PlayableRace from a Blizzard API race ID.
     */
    public static function fromId(int $id): self
    {
        $data = app(PlayableRaceService::class)->find($id);

        return new self(
            id: $id,
            name: Arr::get($data, 'name', 'Unknown Race'),
        );
    }

    /**
     * Build a fallback PlayableRace when the race is unknown or unavailable.
     */
    public static function unknown(): self
    {
        return new self(
            id: null,
            name: 'Unknown Race',
        );
    }

    /**
     * @return array{id: int|null, name: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
