<?php

namespace App\Services\WarcraftLogs\Data;

readonly class Server
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public Region $region,
    ) {}

    /**
     * @param  array{id: int, name: string, slug: string, region: array{id: int, name: string, slug: string}}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            slug: $data['slug'],
            region: Region::fromArray($data['region']),
        );
    }

    /**
     * @return array{id: int, name: string, slug: string, region: array{id: int, name: string, slug: string}}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'region' => $this->region->toArray(),
        ];
    }
}
