<?php

namespace App\Services\Discord;

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

class Channel extends Data
{
    public function __construct(
        /** @var string Snowflake channel identifier */
        #[StringType]
        public readonly string $id,

        public readonly ChannelType $type,

        /** @var string|null Snowflake of the owning guild; absent in some gateway dispatches */
        #[Nullable, StringType]
        public readonly ?string $guild_id = null,

        /** @var int|null Sort position; channels at the same position sort by ID */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $position = null,

        /** @var string|null 1–100 character channel name */
        #[Nullable, StringType, Min(1), Max(100)]
        public readonly ?string $name = null,

        /** @var string|null Channel topic (0–4096 chars for forum/media, 0–1024 for others) */
        #[Nullable, StringType, Max(4096)]
        public readonly ?string $topic = null,

        /** @var bool|null Whether the channel is age-restricted */
        #[Nullable, BooleanType]
        public readonly ?bool $nsfw = null,

        /** @var string|null Snowflake of the last message sent */
        #[Nullable, StringType]
        public readonly ?string $last_message_id = null,

        /** @var int|null Voice channel bitrate in bits per second */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $bitrate = null,

        /** @var int|null Maximum users in a voice channel */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $user_limit = null,

        /** @var int|null Slowmode interval in seconds (0–21600) */
        #[Nullable, IntegerType, Min(0), Max(21600)]
        public readonly ?int $rate_limit_per_user = null,

        /** @var string|null Snowflake of the group DM or thread owner */
        #[Nullable, StringType]
        public readonly ?string $owner_id = null,

        /** @var string|null Snowflake of the bot app that created a group DM */
        #[Nullable, StringType]
        public readonly ?string $application_id = null,

        /** @var bool|null Whether the channel is managed by an application via gdm.join */
        #[Nullable, BooleanType]
        public readonly ?bool $managed = null,

        /** @var string|null Snowflake of the parent category or thread parent (max 50 children per category) */
        #[Nullable, StringType]
        public readonly ?string $parent_id = null,

        /** @var string|null ISO8601 timestamp of the last pinned message; null in GUILD_CREATE events */
        #[Nullable, StringType, DateFormat('Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:sP')]
        public readonly ?string $last_pin_timestamp = null,

        /** @var string|null Voice region override; null means automatic */
        #[Nullable, StringType]
        public readonly ?string $rtc_region = null,

        /** @var int|null Video quality mode: 1 (AUTO) or 2 (FULL/720p) */
        #[Nullable, IntegerType, In(1, 2)]
        public readonly ?int $video_quality_mode = null,

        /** @var int|null Number of messages in a thread (excludes initial/deleted) */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $message_count = null,

        /** @var int|null Approximate member count for a thread; capped at 50 */
        #[Nullable, IntegerType, Min(0), Max(50)]
        public readonly ?int $member_count = null,

        /** @var int|null Default auto-archive duration for threads in minutes */
        #[Nullable, IntegerType, In(60, 1440, 4320, 10080)]
        public readonly ?int $default_auto_archive_duration = null,

        /** @var string|null Computed permissions string; only present in resolved interaction data */
        #[Nullable, StringType]
        public readonly ?string $permissions = null,

        /** @var int|null Channel flags bitfield */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $flags = null,

        /** @var int|null Lifetime count of messages sent in a thread; does not decrement on deletion */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $total_message_sent = null,

        /** @var array<int, string>|null Applied tag snowflakes for forum/media threads (max 5) */
        #[Nullable, ArrayType]
        public readonly ?array $applied_tags = null,

        /** @var int|null Forum sort order: 0 (LATEST_ACTIVITY) or 1 (CREATION_DATE) */
        #[Nullable, IntegerType, In(0, 1)]
        public readonly ?int $default_sort_order = null,

        /** @var int|null Forum layout: 0 (NOT_SET), 1 (LIST_VIEW), 2 (GALLERY_VIEW) */
        #[Nullable, IntegerType, In(0, 1, 2)]
        public readonly ?int $default_forum_layout = null,

        /** @var int|null Slowmode applied to newly created threads; does not update existing threads */
        #[Nullable, IntegerType, Min(0), Max(21600)]
        public readonly ?int $default_thread_rate_limit_per_user = null,

        /** @var array<int, array<string, mixed>>|null Explicit permission overwrite objects */
        #[Nullable, ArrayType]
        public readonly ?array $permission_overwrites = null,

        /** @var array<int, array<string, mixed>>|null Group DM recipients */
        #[Nullable, ArrayType]
        public readonly ?array $recipients = null,

        /** @var string|null Group DM icon hash */
        #[Nullable, StringType]
        public readonly ?string $icon = null,

        /** @var array<int, array<string, mixed>>|null Available tags for GUILD_FORUM/GUILD_MEDIA channels (max 20) */
        #[Nullable, ArrayType]
        public readonly ?array $available_tags = null,

        /** @var array<string, mixed>|null Default reaction emoji for forum/media threads */
        #[Nullable, ArrayType]
        public readonly ?array $default_reaction_emoji = null,

        /** @var array<string, mixed>|null Thread-specific metadata fields */
        #[Nullable, ArrayType]
        public readonly ?array $thread_metadata = null,

        /** @var array<string, mixed>|null Current user's thread member object; only on certain endpoints */
        #[Nullable, ArrayType]
        public readonly ?array $member = null,
    ) {}

    public static function rules(): array
    {
        return [
            'applied_tags' => ['max:5'],
            'available_tags' => ['max:20'],
        ];
    }
}
