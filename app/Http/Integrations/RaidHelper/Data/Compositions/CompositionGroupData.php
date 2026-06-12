<?php

namespace App\Http\Integrations\RaidHelper\Data\Compositions;

use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;
use Spatie\LaravelData\Data;

class CompositionGroupData extends Data
{
    public function __construct(
        /** @var string The name of this group */
        #[StringType]
        public readonly string $name,

        /** @var int The position of this group */
        #[WithCast(BuiltinTypeCast::class, type: 'int')]
        public readonly int $position,
    ) {}
}
