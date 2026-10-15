<?php

namespace App\Http\Integrations\Discord\Data\Messages;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class AttachmentData extends Data
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
        public readonly string $proxyUrl,

        /** @var string|Optional Title of the file */
        #[StringType]
        public readonly string|Optional $title,

        /** @var string|Optional Description / alt-text for the file (max 1024 characters) */
        #[StringType, Max(1024)]
        public readonly string|Optional $description,

        /** @var string|Optional Media type of the attachment */
        #[StringType]
        public readonly string|Optional $contentType,

        /** @var int|Optional|null Height of the file if image or video */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional|null $height,

        /** @var int|Optional|null Width of the file if image or video */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional|null $width,

        /** @var string|Optional Thumbhash placeholder if image or video */
        #[StringType]
        public readonly string|Optional $placeholder,

        /** @var int|Optional Version of the placeholder */
        #[IntegerType, Min(0)]
        public readonly int|Optional $placeholderVersion,

        /** @var bool|Optional Whether the attachment is ephemeral */
        public readonly bool|Optional $ephemeral,

        /** @var float|Optional Duration in seconds for voice messages */
        public readonly float|Optional $durationSecs,

        /** @var string|Optional Base64-encoded waveform for voice messages */
        #[StringType]
        public readonly string|Optional $waveform,

        /** @var int|Optional Combined bitfield of AttachmentFlag values */
        #[IntegerType, Min(0)]
        public readonly int|Optional $flags,

        /** @var string|Optional ISO8601 timestamp; for Clips, when the clip was created */
        #[StringType]
        public readonly string|Optional $clipCreatedAt,
    ) {}
}
