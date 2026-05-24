<?php

namespace App\Services\Discord\Resources;

use App\Services\Discord\Contracts\Resources\Channel as ChannelContract;
use App\Services\Discord\Enums\ChannelType;
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
use Spatie\LaravelData\Optional;

class Channel extends Data implements ChannelContract
{
    public function __construct(
        /** @var string Snowflake channel identifier */
        #[StringType]
        public readonly string $id,

        public readonly ChannelType|Optional $type,

        /** @var string|Optional Snowflake of the owning guild; absent in some gateway dispatches */
        #[Nullable, StringType]
        public readonly string|Optional $guild_id,

        /** @var int|Optional Sort position; channels at the same position sort by ID */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $position,

        /** @var string|Optional 1–100 character channel name */
        #[Nullable, StringType, Min(1), Max(100)]
        public readonly string|Optional $name,

        /** @var string|Optional Channel topic (0–4096 chars for forum/media, 0–1024 for others) */
        #[Nullable, StringType, Max(4096)]
        public readonly string|Optional|null $topic,

        /** @var bool|Optional Whether the channel is age-restricted */
        #[Nullable, BooleanType]
        public readonly bool|Optional $nsfw,

        /** @var string|Optional Snowflake of the last message sent */
        #[Nullable, StringType]
        public readonly string|Optional|null $last_message_id,

        /** @var int|Optional Voice channel bitrate in bits per second */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $bitrate,

        /** @var int|Optional Maximum users in a voice channel */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $user_limit,

        /** @var int|Optional Slowmode interval in seconds (0–21600) */
        #[Nullable, IntegerType, Min(0), Max(21600)]
        public readonly int|Optional $rate_limit_per_user,

        /** @var string|Optional Snowflake of the group DM or thread owner */
        #[Nullable, StringType]
        public readonly string|Optional $owner_id,

        /** @var string|Optional Snowflake of the bot app that created a group DM */
        #[Nullable, StringType]
        public readonly string|Optional $application_id,

        /** @var bool|Optional Whether the channel is managed by an application via gdm.join */
        #[Nullable, BooleanType]
        public readonly bool|Optional $managed,

        /** @var string|Optional Snowflake of the parent category or thread parent (max 50 children per category) */
        #[Nullable, StringType]
        public readonly string|Optional|null $parent_id,

        /** @var string|Optional ISO8601 timestamp of the last pinned message; null in GUILD_CREATE events */
        #[Nullable, StringType, DateFormat('Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:sP')]
        public readonly string|Optional|null $last_pin_timestamp,

        /** @var string|Optional Voice region override; null means automatic */
        #[Nullable, StringType]
        public readonly string|Optional|null $rtc_region,

        /** @var int|Optional Video quality mode: 1 (AUTO) or 2 (FULL/720p) */
        #[Nullable, IntegerType, In(1, 2)]
        public readonly int|Optional $video_quality_mode,

        /** @var int|Optional Number of messages in a thread (excludes initial/deleted) */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $message_count,

        /** @var int|Optional Approximate member count for a thread; capped at 50 */
        #[Nullable, IntegerType, Min(0), Max(50)]
        public readonly int|Optional $member_count,

        /** @var int|Optional Default auto-archive duration for threads in minutes */
        #[Nullable, IntegerType, In(60, 1440, 4320, 10080)]
        public readonly int|Optional $default_auto_archive_duration,

        /** @var string|Optional Computed permissions string; only present in resolved interaction data */
        #[Nullable, StringType]
        public readonly string|Optional $permissions,

        /** @var int|Optional Channel flags bitfield */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $flags,

        /** @var int|Optional Lifetime count of messages sent in a thread; does not decrement on deletion */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $total_message_sent,

        /** @var array<int, string>|Optional Applied tag snowflakes for forum/media threads (max 5) */
        #[ArrayType]
        public readonly array|Optional $applied_tags,

        /** @var int|Optional Forum sort order: 0 (LATEST_ACTIVITY) or 1 (CREATION_DATE) */
        #[Nullable, IntegerType, In(0, 1)]
        public readonly int|Optional|null $default_sort_order,

        /** @var int|Optional Forum layout: 0 (NOT_SET), 1 (LIST_VIEW), 2 (GALLERY_VIEW) */
        #[Nullable, IntegerType, In(0, 1, 2)]
        public readonly int|Optional $default_forum_layout,

        /** @var int|Optional Slowmode applied to newly created threads; does not update existing threads */
        #[Nullable, IntegerType, Min(0), Max(21600)]
        public readonly int|Optional $default_thread_rate_limit_per_user,

        /** @var array<int, array<string, mixed>>|Optional|null Explicit permission overwrite objects */
        #[Nullable, ArrayType]
        public readonly array|Optional|null $permission_overwrites,

        /** @var array<int, array<string, mixed>>|Optional Group DM recipients */
        #[ArrayType]
        public readonly array|Optional $recipients,

        /** @var string|Optional Group DM icon hash */
        #[Nullable, StringType]
        public readonly string|Optional|null $icon,

        /** @var array<int, array<string, mixed>>|Optional Available tags for GUILD_FORUM/GUILD_MEDIA channels (max 20) */
        #[ArrayType]
        public readonly array|Optional $available_tags,

        /** @var array<string, mixed>|Optional|null Default reaction emoji for forum/media threads */
        #[Nullable, ArrayType]
        public readonly array|Optional|null $default_reaction_emoji,

        /** @var array<string, mixed>|Optional Thread-specific metadata fields */
        #[ArrayType]
        public readonly array|Optional $thread_metadata,

        /** @var array<string, mixed>|Optional Current user's thread member object; only on certain endpoints */
        #[ArrayType]
        public readonly array|Optional $member,
    ) {}

    public static function rules(): array
    {
        return [
            'applied_tags' => ['max:5'],
            'available_tags' => ['max:20'],
        ];
    }
}
