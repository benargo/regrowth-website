<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

readonly class PlayerAttendance implements Arrayable
{
    public function __construct(
        public string $name,
        public int $presence,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            presence: $data['presence'],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'presence' => $this->presence,
        ];
    }
}
