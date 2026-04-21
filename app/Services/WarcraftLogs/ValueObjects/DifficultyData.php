<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use Spatie\LaravelData\Data;

class DifficultyData extends Data
{
    /**
     * @param  array<int>  $sizes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $sizes = [],
    ) {}
}
