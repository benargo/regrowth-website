<?php

namespace App\Services\RaidHelper\Resources;

use App\Services\RaidHelper\Casts\IsConfirmed;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;
use Spatie\LaravelData\Data;

class CompSlot extends Data
{
    public function __construct(
        /** @var string The unique identifier for this slot */
        #[StringType]
        public readonly string $id,

        /** @var string The name of the sign-up in this slot */
        #[StringType]
        public readonly string $name,

        /** @var int The group number this slot belongs to */
        #[WithCast(BuiltinTypeCast::class, type: 'int')]
        public readonly int $groupNumber,

        /** @var int The position of this slot within its group */
        #[WithCast(BuiltinTypeCast::class, type: 'int')]
        public readonly int $slotNumber,

        /** @var string The name of the class assigned to this slot */
        #[StringType]
        public readonly string $className,

        /** @var string The emote id for the class assigned to this slot */
        #[StringType]
        public readonly string $classEmoteId,

        /** @var string The name of the spec assigned to this slot */
        #[StringType]
        public readonly string $specName,

        /** @var string The emote id for the spec assigned to this slot */
        #[StringType]
        public readonly string $specEmoteId,

        /** @var bool Whether the sign-up in this slot has been confirmed */
        #[WithCast(IsConfirmed::class)]
        public readonly bool $isConfirmed,

        /** @var string The color associated with this slot */
        #[StringType]
        public readonly string $color,
    ) {}
}
