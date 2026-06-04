<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class GuildMember extends Data
{
    public function __construct(
        /** @var User|Optional The user this guild member represents */
        #[Nullable]
        public readonly User|Optional $user,

        /** @var string|Optional|null This user's guild nickname */
        #[Nullable, StringType]
        public readonly string|Optional|null $nick,

        /** @var string|Optional|null The member's guild avatar hash */
        #[Nullable, StringType]
        public readonly string|Optional|null $avatar,

        /** @var string|Optional|null The member's guild banner hash */
        #[Nullable, StringType]
        public readonly string|Optional|null $banner,

        /** @var array<int, string> Array of role object snowflake IDs */
        #[ArrayType]
        public readonly array $roles,

        /** @var string|Optional|null ISO8601 timestamp of when the user joined the guild */
        #[Nullable, StringType, DateFormat('Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP')]
        public readonly string|Optional|null $joined_at,

        /** @var string|Optional|null ISO8601 timestamp of when the user started boosting the guild */
        #[Nullable, StringType, DateFormat('Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP')]
        public readonly string|Optional|null $premium_since,

        /** @var bool Whether the user is deafened in voice channels */
        #[BooleanType]
        public readonly bool $deaf,

        /** @var bool Whether the user is muted in voice channels */
        #[BooleanType]
        public readonly bool $mute,

        /** @var int Guild member flags represented as a bit set */
        #[IntegerType, Min(0)]
        public readonly int $flags,

        /** @var bool|Optional Whether the user has not yet passed Membership Screening requirements */
        #[Nullable, BooleanType]
        public readonly bool|Optional $pending,

        /** @var string|Optional Total permissions of the member in the channel, including overwrites */
        #[Nullable, StringType]
        public readonly string|Optional $permissions,

        /** @var string|Optional|null ISO8601 timestamp of when the user's timeout expires; null if not timed out */
        #[Nullable, StringType, DateFormat('Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP')]
        public readonly string|Optional|null $communication_disabled_until,

        /** @var array<string, mixed>|Optional|null Data for the member's guild avatar decoration */
        #[Nullable, ArrayType]
        public readonly array|Optional|null $avatar_decoration_data,

        /** @var array<string, mixed>|Optional|null Data for the member's collectibles */
        #[Nullable, ArrayType]
        public readonly array|Optional|null $collectibles,
    ) {}
}
