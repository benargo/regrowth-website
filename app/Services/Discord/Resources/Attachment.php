<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class Attachment extends Data
{
    public function __construct(
        /** @var string Attachment ID (snowflake) */
        #[StringType]
        public readonly string $id,

        /** @var string Name of the file attached */
        #[StringType]
        public readonly string $filename,

        /** @var int Size of the file in bytes */
        #[IntegerType, Min(0)]
        public readonly int $size,

        /** @var string Source URL of the file */
        #[StringType]
        public readonly string $url,

        /** @var string Proxied URL of the file */
        #[StringType]
        public readonly string $proxy_url,

        /** @var string|null Title of the file */
        #[Nullable, StringType]
        public readonly ?string $title = null,

        /** @var string|null Description / alt-text for the file (max 1024 characters) */
        #[Nullable, StringType]
        public readonly ?string $description = null,

        /** @var string|null Media type of the attachment */
        #[Nullable, StringType]
        public readonly ?string $content_type = null,

        /** @var int|null Height of the file if image or video */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $height = null,

        /** @var int|null Width of the file if image or video */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $width = null,

        /** @var string|null Thumbhash placeholder if image or video */
        #[Nullable, StringType]
        public readonly ?string $placeholder = null,

        /** @var int|null Version of the placeholder */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $placeholder_version = null,

        /** @var bool|null Whether the attachment is ephemeral */
        public readonly ?bool $ephemeral = null,

        /** @var float|null Duration in seconds for voice messages */
        public readonly ?float $duration_secs = null,

        /** @var string|null Base64-encoded waveform for voice messages */
        #[Nullable, StringType]
        public readonly ?string $waveform = null,

        /** @var int|null Combined bitfield of AttachmentFlag values */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $flags = null,

        /** @var string|null ISO8601 timestamp; for Clips, when the clip was created */
        #[Nullable, StringType]
        public readonly ?string $clip_created_at = null,
    ) {}
}
