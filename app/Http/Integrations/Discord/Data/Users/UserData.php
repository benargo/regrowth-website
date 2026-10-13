<?php

namespace App\Http\Integrations\Discord\Data\Users;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class UserData extends Data
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

        /** @var string|Optional|null The user's display name, if set */
        #[Nullable, StringType]
        public readonly string|Optional|null $globalName,

        /** @var string|Optional|null The user's avatar hash */
        #[Nullable, StringType]
        public readonly string|Optional|null $avatar,

        /** @var bool|Optional Whether the user belongs to an OAuth2 application */
        #[BooleanType]
        public readonly bool|Optional $bot,

        /** @var bool|Optional Whether the user is an Official Discord System user */
        #[BooleanType]
        public readonly bool|Optional $system,

        /** @var bool|Optional Whether the user has two-factor authentication enabled */
        #[BooleanType]
        public readonly bool|Optional $mfaEnabled,

        /** @var string|Optional|null The user's banner hash */
        #[Nullable, StringType]
        public readonly string|Optional|null $banner,

        /** @var int|Optional|null The user's banner color as a hexadecimal integer */
        #[Nullable, IntegerType]
        public readonly int|Optional|null $accentColor,

        /** @var string|Optional The user's chosen language option */
        #[StringType]
        public readonly string|Optional $locale,

        /** @var bool|Optional Whether the email on this account has been verified */
        #[BooleanType]
        public readonly bool|Optional $verified,

        /** @var string|Optional|null The user's email address */
        #[Nullable, StringType]
        public readonly string|Optional|null $email,

        /** @var int|Optional Bitfield of the user's account flags */
        #[IntegerType, Min(0)]
        public readonly int|Optional $flags,

        /** @var int|Optional The type of Nitro subscription on a user's account */
        #[IntegerType, Min(0)]
        public readonly int|Optional $premiumType,

        /** @var int|Optional Bitfield of the user's publicly visible account flags */
        #[IntegerType, Min(0)]
        public readonly int|Optional $publicFlags,

        /** @var array<string, mixed>|Optional|null Data for the user's avatar decoration */
        #[Nullable, ArrayType]
        public readonly array|Optional|null $avatarDecorationData,

        /** @var array<string, mixed>|Optional|null The user's collectibles */
        #[Nullable, ArrayType]
        public readonly array|Optional|null $collectibles,

        /** @var array<string, mixed>|Optional|null The user's primary guild identity */
        #[Nullable, ArrayType]
        public readonly array|Optional|null $primaryGuild,
    ) {}
}
