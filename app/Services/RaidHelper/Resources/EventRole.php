<?php

namespace App\Services\RaidHelper\Resources;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;
use Spatie\LaravelData\Data;

class EventRole extends Data
{
    public function __construct(
        /** @var string The name of this role */
        #[StringType]
        public readonly string $name,

        /** @var int The maximum allowed sign-ups for this role */
        #[WithCast(BuiltinTypeCast::class, type: 'int')]
        public readonly int $limit,

        /** @var string The emote id of this role */
        #[StringType]
        public readonly string $emoteId,

        /** @var ?string The canonical name of this role */
        #[Nullable, StringType]
        public readonly ?string $cName = null,
    ) {}
}
