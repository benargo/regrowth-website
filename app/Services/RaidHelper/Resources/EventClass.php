<?php

namespace App\Services\RaidHelper\Resources;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;
use Spatie\LaravelData\Data;

class EventClass extends Data
{
    public function __construct(
        /** @var string The name of this class */
        #[StringType]
        public readonly string $name,

        /** @var int The maximum allowed sign-ups for this class */
        #[WithCast(BuiltinTypeCast::class, type: 'int')]
        public readonly int $limit,

        /** @var string The emote id of this class */
        #[StringType]
        public readonly string $emoteId,

        /** @var string The type of this class (primary/default) */
        #[StringType]
        public readonly string $type,

        /** @var array<int, EventSpec> The specs that are applied to this class */
        #[DataCollectionOf(EventSpec::class)]
        public readonly array $specs,

        /** @var ?string The canonical name of this class */
        #[Nullable, StringType]
        public readonly ?string $cName = null,

        /** @var ?string The effective display name of this class (present on default-type classes) */
        #[Nullable, StringType]
        public readonly ?string $effectiveName = null,
    ) {}
}
