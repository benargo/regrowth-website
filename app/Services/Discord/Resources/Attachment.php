<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

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

        /** @var string|Optional Title of the file */
        #[Nullable, StringType]
        public readonly string|Optional $title,

        /** @var string|Optional Description / alt-text for the file (max 1024 characters) */
        #[Nullable, StringType]
        public readonly string|Optional $description,

        /** @var string|Optional Media type of the attachment */
        #[Nullable, StringType]
        public readonly string|Optional $content_type,

        /** @var int|Optional Height of the file if image or video */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $height,

        /** @var int|Optional Width of the file if image or video */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $width,

        /** @var string|Optional Thumbhash placeholder if image or video */
        #[Nullable, StringType]
        public readonly string|Optional $placeholder,

        /** @var int|Optional Version of the placeholder */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $placeholder_version,

        /** @var bool|Optional Whether the attachment is ephemeral */
        public readonly bool|Optional $ephemeral,

        /** @var float|Optional Duration in seconds for voice messages */
        public readonly float|Optional $duration_secs,

        /** @var string|Optional Base64-encoded waveform for voice messages */
        #[Nullable, StringType]
        public readonly string|Optional $waveform,

        /** @var int|Optional Combined bitfield of AttachmentFlag values */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $flags,

        /** @var string|Optional ISO8601 timestamp; for Clips, when the clip was created */
        #[Nullable, StringType]
        public readonly string|Optional $clip_created_at,
    ) {}
}
