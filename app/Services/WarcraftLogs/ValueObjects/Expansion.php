<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

readonly class Expansion implements Arrayable
{
    /**
     * @param  array<Zone>  $zones
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $zones,
    ) {}

    /**
     * @param  array{id: int, name: string, zones: array<array{id: int, name: string}>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            zones: array_map(fn (array $zone) => Zone::fromArray($zone), $data['zones'] ?? []),
        );
    }

    /**
     * @return array{id: int, name: string, zones: array<array{id: int, name: string}>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'zones' => array_map(fn (Zone $zone) => $zone->toArray(), $this->zones),
        ];
    }
}
