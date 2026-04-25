<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class User extends Data
{
    public function __construct(
        /** @var string The user's ID (snowflake) */
        #[StringType]
        public readonly string $id,

        /** @var string The user's username, not unique across the platform */
        #[StringType]
        public readonly string $username,

        /** @var string The user's Discord-tag */
        #[StringType]
        public readonly string $discriminator,

        /** @var string|null The user's display name, if set */
        #[Nullable, StringType]
        public readonly ?string $global_name = null,

        /** @var string|null The user's avatar hash */
        #[Nullable, StringType]
        public readonly ?string $avatar = null,

        /** @var bool|null Whether the user belongs to an OAuth2 application */
        #[Nullable, BooleanType]
        public readonly ?bool $bot = null,

        /** @var bool|null Whether the user is an Official Discord System user */
        #[Nullable, BooleanType]
        public readonly ?bool $system = null,

        /** @var bool|null Whether the user has two-factor authentication enabled */
        #[Nullable, BooleanType]
        public readonly ?bool $mfa_enabled = null,

        /** @var string|null The user's banner hash */
        #[Nullable, StringType]
        public readonly ?string $banner = null,

        /** @var int|null The user's banner color as a hexadecimal integer */
        #[Nullable, IntegerType]
        public readonly ?int $accent_color = null,

        /** @var string|null The user's chosen language option */
        #[Nullable, StringType]
        public readonly ?string $locale = null,

        /** @var bool|null Whether the email on this account has been verified */
        #[Nullable, BooleanType]
        public readonly ?bool $verified = null,

        /** @var string|null The user's email address */
        #[Nullable, StringType]
        public readonly ?string $email = null,

        /** @var int Bitfield of the user's account flags */
        #[IntegerType, Min(0)]
        public readonly int $flags = 0,

        /** @var int|null The type of Nitro subscription on a user's account */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $premium_type = null,

        /** @var int Bitfield of the user's publicly visible account flags */
        #[IntegerType, Min(0)]
        public readonly int $public_flags = 0,

        /** @var array<string, mixed>|null Data for the user's avatar decoration */
        #[Nullable, ArrayType]
        public readonly ?array $avatar_decoration_data = null,

        /** @var array<string, mixed>|null The user's collectibles */
        #[Nullable, ArrayType]
        public readonly ?array $collectibles = null,

        /** @var array<string, mixed>|null The user's primary guild identity */
        #[Nullable, ArrayType]
        public readonly ?array $primary_guild = null,
    ) {}
}
