<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class EmbedMedia extends Data
{
    public function __construct(
        /** @var string Source URL (http(s) and attachments only) */
        #[StringType]
        public readonly string $url,

        /** @var string|Optional Proxied URL of the image */
        #[Nullable, StringType]
        public readonly string|Optional $proxy_url,

        /** @var int|Optional Height of the image */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $height,

        /** @var int|Optional Width of the image */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $width,

        /** @var string|Optional Media type classification */
        #[Nullable, StringType]
        public readonly string|Optional $content_type,

        /** @var string|Optional Thumbhash placeholder */
        #[Nullable, StringType]
        public readonly string|Optional $placeholder,

        /** @var int|Optional Version of the placeholder */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $placeholder_version,

        /** @var string|Optional Alternative text for accessibility */
        #[Nullable, StringType]
        public readonly string|Optional $description,

        /** @var int|Optional Combined bitfield of embed media flags */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $flags,
    ) {}
}
