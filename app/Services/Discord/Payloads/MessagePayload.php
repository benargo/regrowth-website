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

class MessagePayload extends Data
{
    public function __construct(
        /** @var string|null Message contents (up to 2000 characters) */
        #[Nullable, StringType]
        public readonly ?string $content = null,

        /** @var int|string|null Used to verify a message was sent (up to 25 characters) */
        public readonly int|string|null $nonce = null,

        /** @var bool|null True if this is a TTS message */
        #[Nullable, BooleanType]
        public readonly ?bool $tts = null,

        /** @var array<Embed>|null Up to 10 rich embeds (up to 6000 characters) */
        #[DataCollectionOf(Embed::class)]
        public readonly ?array $embeds = null,

        /** @var MessageReference|null Include to make your message a reply or a forward */
        public readonly ?MessageReference $message_reference = null,

        /** @var array<string>|null IDs of up to 3 stickers in the server to send in the message (snowflakes) */
        public readonly ?array $sticker_ids = null,

        /** @var string|null JSON-encoded body of non-file params, only for multipart/form-data requests */
        #[Nullable, StringType]
        public readonly ?string $payload_json = null,

        /** @var array<Attachment>|null Attachment objects with filename and description */
        #[DataCollectionOf(Attachment::class)]
        public readonly ?array $attachments = null,

        /** @var int|null Message flags combined as a bitfield */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $flags = null,

        /** @var bool|null If true and nonce is present, enforces uniqueness in the past few minutes */
        #[Nullable, BooleanType]
        public readonly ?bool $enforce_nonce = null,
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
