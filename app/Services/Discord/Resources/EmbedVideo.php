<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class EmbedVideo extends Data
{
    public function __construct(
        /** @var string|null Source URL of video */
        #[Nullable, StringType]
        public readonly ?string $url = null,

        /** @var string|null Proxied URL of the video */
        #[Nullable, StringType]
        public readonly ?string $proxy_url = null,

        /** @var int|null Height of the video */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $height = null,

        /** @var int|null Width of the video */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $width = null,

        /** @var string|null Media type format */
        #[Nullable, StringType]
        public readonly ?string $content_type = null,

        /** @var string|null Thumbhash placeholder of the video */
        #[Nullable, StringType]
        public readonly ?string $placeholder = null,

        /** @var int|null Version of the placeholder */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $placeholder_version = null,

        /** @var string|null Alternative text for video content */
        #[Nullable, StringType]
        public readonly ?string $description = null,

        /** @var int|null Combined embed media bitfield */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $flags = null,
    ) {}
}
