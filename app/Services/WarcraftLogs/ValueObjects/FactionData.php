<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use Spatie\LaravelData\Data;

class FactionData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}
}
