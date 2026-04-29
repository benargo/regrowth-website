<?php

namespace App\Services\Discord\Resources;

use App\Services\Discord\Enums\MessageReferenceType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class MessageReference extends Data
{
    public function __construct(
        /** @var MessageReferenceType|null Type of reference; defaults to Default if unset */
        public readonly ?MessageReferenceType $type = null,

        /** @var string|null ID of the originating message (snowflake) */
        #[Nullable, StringType]
        public readonly ?string $message_id = null,

        /** @var string|null ID of the originating message's channel (snowflake); required for forwards */
        #[Nullable, StringType]
        public readonly ?string $channel_id = null,

        /** @var string|null ID of the originating message's guild (snowflake) */
        #[Nullable, StringType]
        public readonly ?string $guild_id = null,

        /** @var bool|null Whether to error if the referenced message doesn't exist; default true */
        #[Nullable, BooleanType]
        public readonly ?bool $fail_if_not_exists = null,
    ) {}
}
