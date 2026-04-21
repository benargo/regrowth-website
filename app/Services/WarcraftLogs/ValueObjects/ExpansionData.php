<?php

namespace App\Services\WarcraftLogs\ValueObjects;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class ExpansionData extends Data
{
    /**
     * @param  array<ZoneData>  $zones
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        #[DataCollectionOf(ZoneData::class)]
        public readonly array $zones = [],
    ) {}
}
