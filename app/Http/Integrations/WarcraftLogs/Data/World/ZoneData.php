<?php

namespace App\Http\Integrations\WarcraftLogs\Data\World;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

final class ZoneData extends Data
{
    /**
     * @param  array<int, DifficultyData>  $difficulties
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        #[DataCollectionOf(DifficultyData::class)]
        public readonly array $difficulties = [],
        public readonly bool $frozen = false,
        public readonly ?ExpansionData $expansion = null,
    ) {}
}
