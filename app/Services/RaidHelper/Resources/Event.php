<?php

namespace App\Services\RaidHelper\Resources;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class Event extends Data
{
    public function __construct(
        /** @var string The message id of this event */
        #[StringType]
        public readonly string $id,

        /** @var string The user id of this events leader */
        #[StringType]
        public readonly string $leaderId,

        /** @var string The name of this events leader */
        #[StringType]
        public readonly string $leaderName,

        /** @var string This events channel id */
        #[StringType]
        public readonly string $channelId,

        /** @var string The event title */
        #[StringType]
        public readonly string $title,

        /** @var string The event description */
        #[StringType]
        public readonly string $description,

        /** @var CarbonInterface The unix timestamp of when this event will start */
        #[WithCast(DateTimeInterfaceCast::class, format: 'U', type: Carbon::class)]
        public readonly CarbonInterface $startTime,

        /** @var CarbonInterface The unix timestamp of when this event will end */
        #[WithCast(DateTimeInterfaceCast::class, format: 'U', type: Carbon::class)]
        public readonly CarbonInterface $endTime,

        /** @var CarbonInterface The unix timestamp of when this event will close and deny further sign-ups */
        #[MapInputName('closeTime')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'U', type: Carbon::class)]
        public readonly CarbonInterface $closingTime,

        /** @var CarbonInterface The unix timestamp of when this event was updated last */
        #[WithCast(DateTimeInterfaceCast::class, format: 'U', type: Carbon::class)]
        public readonly CarbonInterface $lastUpdated,

        /** @var string The current embed color in RGB format */
        #[StringType]
        public readonly string $color,

        /** @var string|null The server id of this event */
        #[Nullable, StringType]
        public readonly ?string $serverId = null,

        /** @var string|null This events channel name */
        #[Nullable, StringType]
        public readonly ?string $channelName = null,

        /** @var string|null The type of this events channel */
        #[Nullable, StringType]
        public readonly ?string $channelType = null,

        /** @var string|null The id of this events template */
        #[Nullable, StringType]
        public readonly ?string $templateId = null,

        /** @var string|null The emote id of the emote used to represent the template of this event */
        #[Nullable, StringType]
        public readonly ?string $templateEmoteId = null,

        /** @var string|null The raw date string of when this event will start */
        #[Nullable, StringType]
        public readonly ?string $date = null,

        /** @var string|null The raw time string of when this event will start */
        #[Nullable, StringType]
        public readonly ?string $time = null,

        /** @var EventAdvancedSettings|null The advanced settings for this event */
        public readonly ?EventAdvancedSettings $advancedSettings = null,

        /** @var array<int, EventClass>|null The classes that are applied to this event */
        #[DataCollectionOf(EventClass::class)]
        public readonly ?array $classes = null,

        /** @var array<int, EventRole>|null The roles that are applied to this event */
        #[DataCollectionOf(EventRole::class)]
        public readonly ?array $roles = null,

        /** @var array<int, SignUp>|null The current sign-ups on this event */
        #[DataCollectionOf(SignUp::class)]
        public readonly ?array $signUps = null,

        /** @var string|null The softres id attached to this event */
        #[Nullable, StringType]
        public readonly ?string $softresId = null,

        /** @var string|null The image URL of the event */
        #[Nullable, StringType]
        public readonly ?string $imageUrl = null,

        /** @var int|null The number of sign-ups on this event */
        #[WithCast(BuiltinTypeCast::class, type: 'int')]
        public readonly ?int $signUpCount = null,

        /** @var string|null The scheduled id of this event */
        #[Nullable, StringType]
        public readonly ?string $scheduledId = null,

        /** @var string|null The display title of this event */
        #[Nullable, StringType]
        public readonly ?string $displayTitle = null,

        /** @var array|null The announcements for this event */
        public readonly ?array $announcements = null,
    ) {}

    /**
     * Get the User model for the leader of this event, or null if the leader cannot be found.
     */
    public function user(): ?User
    {
        return User::find($this->leaderId);
    }

    /**
     * Get the custom validation rules for this resource.
     */
    public static function rules(): array
    {
        return [
            'startTime' => ['before:endTime', 'before_or_equal:closingTime'],
            'endTime' => ['after:startTime', 'after:closingTime'],
            'closingTime' => ['before_or_equal:startTime'],
        ];
    }
}
