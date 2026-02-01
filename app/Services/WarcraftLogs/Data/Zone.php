<?php

namespace App\Services\WarcraftLogs\Data;

readonly class Zone
{
    public function __construct(
        /**
         * The ID of the zone.
         */
        public int $id,

        /**
         * The name of the zone.
         */
        public string $name,
    ) {}

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
