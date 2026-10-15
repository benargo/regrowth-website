<?php

namespace App\Http\Integrations\Discord\Data\Messages;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class EmbedMediaData extends Data
{
    public function __construct(
        /** @var string Source URL (http(s) and attachments only) */
        #[StringType]
        public readonly string $url,

        /** @var string|Optional Proxied URL of the image */
        #[StringType]
        public readonly string|Optional $proxyUrl,

        /** @var int|Optional Height of the image */
        #[IntegerType, Min(0)]
        public readonly int|Optional $height,

        /** @var int|Optional Width of the image */
        #[IntegerType, Min(0)]
        public readonly int|Optional $width,

        /** @var string|Optional Media type classification */
        #[StringType]
        public readonly string|Optional $contentType,

        /** @var string|Optional Thumbhash placeholder */
        #[StringType]
        public readonly string|Optional $placeholder,

        /** @var int|Optional Version of the placeholder */
        #[IntegerType, Min(0)]
        public readonly int|Optional $placeholderVersion,

        /** @var string|Optional Alternative text for accessibility */
        #[StringType]
        public readonly string|Optional $description,

        /** @var int|Optional Combined bitfield of embed media flags */
        #[IntegerType, Min(0)]
        public readonly int|Optional $flags,
    ) {}
}
