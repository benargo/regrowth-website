<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

readonly class Difficulty implements Arrayable, JsonSerializable
{
    /**
     * @param  array<int>  $sizes
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $sizes = [],
    ) {}

    /**
     * @param  array{id: int, name: string, sizes?: array<int>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            sizes: $data['sizes'] ?? [],
        );
    }

    /**
     * @return array{id: int, name: string, sizes: array<int>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sizes' => $this->sizes,
        ];
    }

    /**
     * @return array{id: int, name: string, sizes: array<int>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
