<?php

namespace App\Services\WarcraftLogs\Data;

readonly class Faction
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    /**
     * @param  array{id: int, name: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
        );
    }

    /**
     * @return array{id: int, name: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
