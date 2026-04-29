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

class GuildMember extends Data
{
    public function __construct(
        /** @var User|null The user this guild member represents */
        #[Nullable]
        public readonly ?User $user = null,

        /** @var string|null This user's guild nickname */
        #[Nullable, StringType]
        public readonly ?string $nick = null,

        /** @var string|null The member's guild avatar hash */
        #[Nullable, StringType]
        public readonly ?string $avatar = null,

        /** @var string|null The member's guild banner hash */
        #[Nullable, StringType]
        public readonly ?string $banner = null,

        /** @var array<int, string> Array of role object snowflake IDs */
        #[ArrayType]
        public readonly array $roles = [],

        /** @var string|null ISO8601 timestamp of when the user joined the guild */
        #[Nullable, StringType, DateFormat('Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP')]
        public readonly ?string $joined_at = null,

        /** @var string|null ISO8601 timestamp of when the user started boosting the guild */
        #[Nullable, StringType, DateFormat('Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP')]
        public readonly ?string $premium_since = null,

        /** @var bool Whether the user is deafened in voice channels */
        #[BooleanType]
        public readonly bool $deaf = false,

        /** @var bool Whether the user is muted in voice channels */
        #[BooleanType]
        public readonly bool $mute = false,

        /** @var int Guild member flags represented as a bit set */
        #[IntegerType, Min(0)]
        public readonly int $flags = 0,

        /** @var bool|null Whether the user has not yet passed Membership Screening requirements */
        #[Nullable, BooleanType]
        public readonly ?bool $pending = null,

        /** @var string|null Total permissions of the member in the channel, including overwrites */
        #[Nullable, StringType]
        public readonly ?string $permissions = null,

        /** @var string|null ISO8601 timestamp of when the user's timeout expires; null if not timed out */
        #[Nullable, StringType, DateFormat('Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP')]
        public readonly ?string $communication_disabled_until = null,

        /** @var array<string, mixed>|null Data for the member's guild avatar decoration */
        #[Nullable, ArrayType]
        public readonly ?array $avatar_decoration_data = null,

        /** @var array<string, mixed>|null Data for the member's collectibles */
        #[Nullable, ArrayType]
        public readonly ?array $collectibles = null,
    ) {}
}
