<?php

namespace App\Http\Integrations\Discord\Data\Channels;

use App\Http\Integrations\Discord\Enums\ChannelType;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class ChannelData extends Data
{
    public function __construct(
        /** @var string Snowflake channel identifier */
        #[StringType]
        public readonly string $id,

        public readonly ChannelType|Optional $type,

        /** @var string|Optional Snowflake of the owning guild; absent in some gateway dispatches */
        #[StringType]
        public readonly string|Optional $guildId,

        /** @var int|Optional Sort position; channels at the same position sort by ID */
        #[IntegerType, Min(0)]
        public readonly int|Optional $position,

        /** @var string|Optional|null 1–100 character channel name */
        #[Nullable, StringType, Min(1), Max(100)]
        public readonly string|Optional|null $name,

        /** @var string|Optional|null Channel topic (0–4096 chars for forum/media, 0–1024 for others) */
        #[Nullable, StringType, Max(4096)]
        public readonly string|Optional|null $topic,

        /** @var bool|Optional Whether the channel is age-restricted */
        #[BooleanType]
        public readonly bool|Optional $nsfw,

        /** @var string|Optional|null Snowflake of the last message sent */
        #[Nullable, StringType]
        public readonly string|Optional|null $lastMessageId,

        /** @var int|Optional Voice channel bitrate in bits per second */
        #[IntegerType, Min(0)]
        public readonly int|Optional $bitrate,

        /** @var int|Optional Maximum users in a voice channel */
        #[IntegerType, Min(0)]
        public readonly int|Optional $userLimit,

        /** @var int|Optional Slowmode interval in seconds (0–21600) */
        #[IntegerType, Min(0), Max(21600)]
        public readonly int|Optional $rateLimitPerUser,

        /** @var string|Optional Snowflake of the group DM or thread owner */
        #[StringType]
        public readonly string|Optional $ownerId,

        /** @var string|Optional|null Snowflake of the bot app that created a group DM */
        #[Nullable, StringType]
        public readonly string|Optional|null $applicationId,

        /** @var bool|Optional Whether the channel is managed by an application via gdm.join */
        #[BooleanType]
        public readonly bool|Optional $managed,

        /** @var string|Optional|null Snowflake of the parent category or thread parent (max 50 children per category) */
        #[Nullable, StringType]
        public readonly string|Optional|null $parentId,

        /** @var string|Optional|null ISO8601 timestamp of the last pinned message; null in GUILD_CREATE events */
        #[Nullable, StringType, DateFormat('Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP')]
        public readonly string|Optional|null $lastPinTimestamp,

        /** @var string|Optional|null Voice region override; null means automatic */
        #[Nullable, StringType]
        public readonly string|Optional|null $rtcRegion,

        /** @var int|Optional Video quality mode: 1 (AUTO) or 2 (FULL/720p) */
        #[IntegerType, In(1, 2)]
        public readonly int|Optional $videoQualityMode,

        /** @var int|Optional Number of messages in a thread (excludes initial/deleted) */
        #[IntegerType, Min(0)]
        public readonly int|Optional $messageCount,

        /** @var int|Optional Approximate member count for a thread; capped at 50 */
        #[IntegerType, Min(0), Max(50)]
        public readonly int|Optional $memberCount,

        /** @var int|Optional Default auto-archive duration for threads in minutes */
        #[IntegerType, In(60, 1440, 4320, 10080)]
        public readonly int|Optional $defaultAutoArchiveDuration,

        /** @var string|Optional Computed permissions string; only present in resolved interaction data */
        #[StringType]
        public readonly string|Optional $permissions,

        /** @var int|Optional Channel flags bitfield */
        #[IntegerType, Min(0)]
        public readonly int|Optional $flags,

        /** @var int|Optional Lifetime count of messages sent in a thread; does not decrement on deletion */
        #[IntegerType, Min(0)]
        public readonly int|Optional $totalMessageSent,

        /** @var array<int, string>|Optional Applied tag snowflakes for forum/media threads (max 5) */
        #[ArrayType]
        public readonly array|Optional $appliedTags,

        /** @var int|Optional|null Forum sort order: 0 (LATEST_ACTIVITY) or 1 (CREATION_DATE) */
        #[Nullable, IntegerType, In(0, 1)]
        public readonly int|Optional|null $defaultSortOrder,

        /** @var int|Optional Forum layout: 0 (NOT_SET), 1 (LIST_VIEW), 2 (GALLERY_VIEW) */
        #[IntegerType, In(0, 1, 2)]
        public readonly int|Optional $defaultForumLayout,

        /** @var int|Optional Slowmode applied to newly created threads; does not update existing threads */
        #[IntegerType, Min(0), Max(21600)]
        public readonly int|Optional $defaultThreadRateLimitPerUser,

        /** @var array<int, array<string, mixed>>|Optional|null Explicit permission overwrite objects */
        #[Nullable, ArrayType]
        public readonly array|Optional|null $permissionOverwrites,

        /** @var array<int, array<string, mixed>>|Optional Group DM recipients */
        #[ArrayType]
        public readonly array|Optional $recipients,

        /** @var string|Optional|null Group DM icon hash */
        #[Nullable, StringType]
        public readonly string|Optional|null $icon,

        /** @var array<int, array<string, mixed>>|Optional Available tags for GUILD_FORUM/GUILD_MEDIA channels (max 20) */
        #[ArrayType]
        public readonly array|Optional $availableTags,

        /** @var array<string, mixed>|Optional|null Default reaction emoji for forum/media threads */
        #[Nullable, ArrayType]
        public readonly array|Optional|null $defaultReactionEmoji,

        /** @var array<string, mixed>|Optional Thread-specific metadata fields */
        #[ArrayType]
        public readonly array|Optional $threadMetadata,

        /** @var array<string, mixed>|Optional Current user's thread member object; only on certain endpoints */
        #[ArrayType]
        public readonly array|Optional $member,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'applied_tags' => ['max:5'],
            'available_tags' => ['max:20'],
        ];
    }
}
