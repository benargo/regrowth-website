<?php

namespace App\Services\Discord\Payloads;

use App\Services\Discord\Resources\Attachment;
use App\Services\Discord\Resources\Embed;
use App\Services\Discord\Resources\MessageReference;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class MessagePayload extends Data
{
    public function __construct(
        /** @var string|Optional Message contents (up to 2000 characters) */
        #[Nullable, StringType]
        public readonly string|Optional $content,

        /** @var int|string|Optional Used to verify a message was sent (up to 25 characters) */
        public readonly int|string|Optional $nonce,

        /** @var bool|Optional True if this is a TTS message */
        #[Nullable, BooleanType]
        public readonly bool|Optional $tts,

        /** @var array<Embed>|Optional Up to 10 rich embeds (up to 6000 characters) */
        #[DataCollectionOf(Embed::class)]
        public readonly array|Optional $embeds,

        /** @var MessageReference|Optional Include to make your message a reply or a forward */
        public readonly MessageReference|Optional $message_reference,

        /** @var array<string>|Optional IDs of up to 3 stickers in the server to send in the message (snowflakes) */
        public readonly array|Optional $sticker_ids,

        /** @var string|Optional JSON-encoded body of non-file params, only for multipart/form-data requests */
        #[Nullable, StringType]
        public readonly string|Optional $payload_json,

        /** @var array<Attachment>|Optional Attachment objects with filename and description */
        #[DataCollectionOf(Attachment::class)]
        public readonly array|Optional $attachments,

        /** @var int|Optional Message flags combined as a bitfield */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $flags,

        /** @var bool|Optional If true and nonce is present, enforces uniqueness in the past few minutes */
        #[Nullable, BooleanType]
        public readonly bool|Optional $enforce_nonce,
    ) {}

    public static function rules(): array
    {
        return [
            'content' => ['max:2000'],
            'embeds' => ['max:10'],
            'sticker_ids' => ['max:3'],
        ];
    }
}
