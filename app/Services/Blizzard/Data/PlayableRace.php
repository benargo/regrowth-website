<?php

namespace App\Services\Blizzard\Data;

readonly class PlayableRace
{
    public function __construct(
        public ?int $id,
        public string $name,
    ) {}

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
