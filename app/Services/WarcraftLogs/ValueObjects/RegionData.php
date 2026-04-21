<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use Spatie\LaravelData\Data;

class RegionData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
    ) {}
}
