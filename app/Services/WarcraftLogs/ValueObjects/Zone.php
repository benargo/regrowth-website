<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

readonly class Zone implements Arrayable
{
    /**
     * @param  array<Difficulty>  $difficulties
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $difficulties = [],
        public bool $frozen = false,
        public ?Expansion $expansion = null,
    ) {}

    /**
     * @param  array{id: int, name: string, difficulties?: array<array{id: int, name: string, sizes: array<int>}>, frozen?: bool, expansion?: array{id: int, name: string, zones?: array<array{id: int, name: string}>}}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            difficulties: array_map(
                fn (array $d) => Difficulty::fromArray($d),
                $data['difficulties'] ?? [],
            ),
            frozen: $data['frozen'] ?? false,
            expansion: isset($data['expansion']) ? Expansion::fromArray($data['expansion']) : null,
        );
    }

    /**
     * @return array{id: int, name: string, difficulties: array<array{id: int, name: string, sizes: array<int>}>, frozen: bool, expansion: array{id: int, name: string, zones: array<mixed>}|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'difficulties' => array_map(fn (Difficulty $d) => $d->toArray(), $this->difficulties),
            'frozen' => $this->frozen,
            'expansion' => $this->expansion?->toArray(),
        ];
    }
}
