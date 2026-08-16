<?php

namespace App\Http\Integrations\RaidHelper\Data\Compositions;

use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;
use Spatie\LaravelData\Data;

class CompositionDividerData extends Data
{
    public function __construct(
        /** @var string The name of this divider */
        #[StringType]
        public readonly string $name,

        /** @var int The position of this divider */
        #[WithCast(BuiltinTypeCast::class, type: 'int')]
        public readonly int $position,
    ) {}
}
