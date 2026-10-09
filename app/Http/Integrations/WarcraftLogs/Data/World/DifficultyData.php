<?php

namespace App\Http\Integrations\WarcraftLogs\Data\World;

use Spatie\LaravelData\Data;

final class DifficultyData extends Data
{
    /**
     * @param  array<int, int>  $sizes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly array $sizes = [],
    ) {}
}
