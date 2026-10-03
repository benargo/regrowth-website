<?php

namespace App\Http\Integrations\WarcraftLogs\Data\World;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

final class ExpansionData extends Data
{
    /**
     * @param  array<int, ZoneData>  $zones
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        #[DataCollectionOf(ZoneData::class)]
        public readonly array $zones = [],
    ) {}
}
