<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

readonly class Server implements Arrayable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public Region $region,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            slug: $data['slug'],
            region: Region::fromArray($data['region']),
        );
    }

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
