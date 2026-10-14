<?php

namespace App\Http\Integrations\Discord\Data\Guilds;

use App\Http\Integrations\Discord\Data\Users\UserData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class GuildMemberData extends Data
{
    public function __construct(
        /** @var UserData|Optional The user this guild member represents */
        public readonly UserData|Optional $user,

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

        /** @var string|null ISO8601 timestamp of when the user joined the guild; null for guest members */
        #[Present, Nullable, StringType, DateFormat('Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP')]
        public readonly ?string $joinedAt,

        /** @var string|Optional|null ISO8601 timestamp of when the user started boosting the guild */
        #[Nullable, StringType, DateFormat('Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP')]
        public readonly string|Optional|null $premiumSince,

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
        #[BooleanType]
        public readonly bool|Optional $pending,

        /** @var string|Optional Total permissions of the member in the channel, including overwrites */
        #[StringType]
        public readonly string|Optional $permissions,

        /** @var string|Optional|null ISO8601 timestamp of when the user's timeout expires; null if not timed out */
        #[Nullable, StringType, DateFormat('Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP')]
        public readonly string|Optional|null $communicationDisabledUntil,

        /** @var array<string, mixed>|Optional|null Data for the member's guild avatar decoration */
        #[Nullable, ArrayType]
        public readonly array|Optional|null $avatarDecorationData,

        /** @var array<string, mixed>|Optional|null Data for the member's collectibles */
        #[Nullable, ArrayType]
        public readonly array|Optional|null $collectibles,
    ) {}
}
