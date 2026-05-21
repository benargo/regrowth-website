<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class EmbedVideo extends Data
{
    public function __construct(
        /** @var string|Optional Source URL of video */
        #[Nullable, StringType]
        public readonly string|Optional $url,

        /** @var string|Optional Proxied URL of the video */
        #[Nullable, StringType]
        public readonly string|Optional $proxy_url,

        /** @var int|Optional Height of the video */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $height,

        /** @var int|Optional Width of the video */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $width,

        /** @var string|Optional Media type format */
        #[Nullable, StringType]
        public readonly string|Optional $content_type,

        /** @var string|Optional Thumbhash placeholder of the video */
        #[Nullable, StringType]
        public readonly string|Optional $placeholder,

        /** @var int|Optional Version of the placeholder */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $placeholder_version,

        /** @var string|Optional Alternative text for video content */
        #[Nullable, StringType]
        public readonly string|Optional $description,

        /** @var int|Optional Combined embed media bitfield */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $flags,
    ) {}
}
