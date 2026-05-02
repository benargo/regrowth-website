<?php

namespace App\Services\RaidHelper\Resources;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;
use Spatie\LaravelData\Data;

class EventSpec extends Data
{
    public function __construct(
        /** @var string The name of this spec */
        #[StringType]
        public readonly string $name,

        /** @var string The emote id of this spec */
        #[StringType]
        public readonly string $emoteId,

        /** @var string The name of the role that this spec belongs to */
        #[StringType]
        public readonly string $roleName,

        /** @var string The emote id of the role that this spec belongs to */
        #[StringType]
        public readonly string $roleEmoteId,

        /** @var string|null The canonical name of this spec */
        #[Nullable, StringType]
        public readonly ?string $cName = null,

        /** @var int The maximum allowed sign-ups for this spec */
        #[WithCast(BuiltinTypeCast::class, type: 'int')]
        public readonly int $limit = 999,
    ) {}
}
