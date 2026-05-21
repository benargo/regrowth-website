<?php

namespace App\Services\Discord\Resources;

use App\Services\Discord\Enums\MessageReferenceType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class MessageReference extends Data
{
    public function __construct(
        /** @var MessageReferenceType|Optional Type of reference; defaults to Default if unset */
        public readonly MessageReferenceType|Optional $type,

        /** @var string|Optional ID of the originating message (snowflake) */
        #[Nullable, StringType]
        public readonly string|Optional $message_id,

        /** @var string|Optional ID of the originating message's channel (snowflake); required for forwards */
        #[Nullable, StringType]
        public readonly string|Optional $channel_id,

        /** @var string|Optional ID of the originating message's guild (snowflake) */
        #[Nullable, StringType]
        public readonly string|Optional $guild_id,

        /** @var bool|Optional Whether to error if the referenced message doesn't exist; default true */
        #[Nullable, BooleanType]
        public readonly bool|Optional $fail_if_not_exists,
    ) {}
}
