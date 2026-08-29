<?php

namespace App\Http\Integrations\RaidHelper\Data\Zones;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class ZoneBossData extends Data
{
    public function __construct(
        /** @var int The local bosses.id of this boss */
        #[IntegerType]
        public readonly int $id,

        /** @var string The name of this boss */
        #[StringType]
        public readonly string $name,

        /** @var ?int A sequencing hint for this boss within its zone */
        #[Nullable, IntegerType]
        public readonly ?int $order = null,
    ) {}
}
