<?php

namespace App\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Enums\MessageReferenceType;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class MessageReferenceData extends Data
{
    public function __construct(
        /** @var MessageReferenceType|Optional Type of reference; defaults to Default if unset */
        public readonly MessageReferenceType|Optional $type,

        /** @var string|Optional ID of the originating message (snowflake) */
        #[StringType]
        public readonly string|Optional $messageId,

        /** @var string|Optional ID of the originating message's channel (snowflake); required for forwards */
        #[StringType]
        public readonly string|Optional $channelId,

        /** @var string|Optional ID of the originating message's guild (snowflake) */
        #[StringType]
        public readonly string|Optional $guildId,

        /** @var bool|Optional Whether to error if the referenced message doesn't exist; default true */
        #[BooleanType]
        public readonly bool|Optional $failIfNotExists,
    ) {}
}
