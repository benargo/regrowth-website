<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class EmbedMedia extends Data
{
    public function __construct(
        /** @var string Source URL (http(s) and attachments only) */
        #[StringType]
        public readonly string $url,

        /** @var string|null Proxied URL of the image */
        #[Nullable, StringType]
        public readonly ?string $proxy_url = null,

        /** @var int|null Height of the image */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $height = null,

        /** @var int|null Width of the image */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $width = null,

        /** @var string|null Media type classification */
        #[Nullable, StringType]
        public readonly ?string $content_type = null,

        /** @var string|null Thumbhash placeholder */
        #[Nullable, StringType]
        public readonly ?string $placeholder = null,

        /** @var int|null Version of the placeholder */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $placeholder_version = null,

        /** @var string|null Alternative text for accessibility */
        #[Nullable, StringType]
        public readonly ?string $description = null,

        /** @var int|null Combined bitfield of embed media flags */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $flags = null,
    ) {}
}
