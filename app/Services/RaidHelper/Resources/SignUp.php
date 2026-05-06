<?php

namespace App\Services\RaidHelper\Resources;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class SignUp extends Data
{
    public function __construct(
        /** @var string The name of the user */
        #[StringType]
        public readonly string $name,

        /** @var int The id of this sign-up */
        #[IntegerType, Min(0)]
        public readonly int $id,

        /** @var string The Discord id of the user */
        #[StringType]
        public readonly string $userId,

        /** @var int The unix timestamp of the registration time */
        #[IntegerType, Min(0)]
        public readonly int $entryTime,

        /** @var string|null The status of the sign-up (primary/queued) */
        #[Nullable, StringType]
        public readonly ?string $status = null,

        /** @var int|null The order number of this sign-up */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $position = null,

        /** @var string|null The class name of the sign-up */
        #[Nullable, StringType]
        public readonly ?string $className = null,

        /** @var string|null The class emote id of the sign-up */
        #[Nullable, StringType]
        public readonly ?string $classEmoteId = null,

        /** @var string|null The spec name of the sign-up */
        #[Nullable, StringType]
        public readonly ?string $specName = null,

        /** @var string|null The spec emote id of the sign-up */
        #[Nullable, StringType]
        public readonly ?string $specEmoteId = null,

        /** @var string|null The role name of the sign-up */
        #[Nullable, StringType]
        public readonly ?string $roleName = null,

        /** @var string|null The role emote id of the sign-up */
        #[Nullable, StringType]
        public readonly ?string $roleEmoteId = null,

        /** @var string|null The canonical class name of the sign-up */
        #[Nullable, StringType]
        public readonly ?string $cClassName = null,

        /** @var string|null The canonical spec name of the sign-up */
        #[Nullable, StringType]
        public readonly ?string $cSpecName = null,

        /** @var string|null The canonical role name of the sign-up */
        #[Nullable, StringType]
        public readonly ?string $cRoleName = null,
    ) {}
}
