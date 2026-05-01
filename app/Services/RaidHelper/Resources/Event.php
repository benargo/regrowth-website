<?php

namespace App\Services\RaidHelper\Resources;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class Event extends Data
{
    public function __construct(
        /** @var string The message id of this event */
        #[StringType]
        public readonly string $id,

        /** @var string The server id of this event */
        #[StringType]
        public readonly string $serverId,

        /** @var string The user id of this events leader */
        #[StringType]
        public readonly string $leaderId,

        /** @var string The name of this events leader */
        #[StringType]
        public readonly string $leaderName,

        /** @var string This events channel id */
        #[StringType]
        public readonly string $channelId,

        /** @var string This events channel name */
        #[StringType]
        public readonly string $channelName,

        /** @var string The type of this events channel */
        #[StringType]
        public readonly string $channelType,

        /** @var string The id of this events template */
        #[StringType]
        public readonly string $templateId,

        /** @var string The emote id of the emote used to represent the template of this event */
        #[StringType]
        public readonly string $templateEmoteId,

        /** @var string The event title */
        #[StringType]
        public readonly string $title,

        /** @var string The event description */
        #[StringType]
        public readonly string $description,

        /** @var int The unix timestamp of when this event will start */
        #[IntegerType, Min(0)]
        public readonly int $startTime,

        /** @var int The unix timestamp of when this event will end */
        #[IntegerType, Min(0)]
        public readonly int $endTime,

        /** @var int The unix timestamp of when this event will close and deny further sign-ups */
        #[IntegerType, Min(0)]
        public readonly int $closingTime,

        /** @var string The raw date string of when this event will start */
        #[StringType]
        public readonly string $date,

        /** @var string The raw time string of when this event will start */
        #[StringType]
        public readonly string $time,

        /** @var EventAdvancedSettings The advanced settings for this event */
        public readonly EventAdvancedSettings $advancedSettings,

        /** @var array<int, EventClass> The classes that are applied to this event */
        #[DataCollectionOf(EventClass::class)]
        public readonly array $classes,

        /** @var array<int, Role> The roles that are applied to this event */
        #[DataCollectionOf(EventRole::class)]
        public readonly array $roles,

        /** @var array<int, SignUp> The current sign-ups on this event */
        #[DataCollectionOf(SignUp::class)]
        public readonly array $signUps,

        /** @var int The unix timestamp of when this event was updated last */
        #[IntegerType, Min(0)]
        public readonly int $lastUpdated,

        /** @var string The current embed color in RGB format */
        #[StringType]
        public readonly string $color,

        /** @var string|null The softres id attached to this event */
        #[Nullable, StringType]
        public readonly ?string $softresId = null,
    ) {}
}
