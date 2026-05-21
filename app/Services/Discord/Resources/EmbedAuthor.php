<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class EmbedAuthor extends Data
{
    public function __construct(
        /** @var string Name of author */
        #[StringType]
        public readonly string $name,

        /** @var string|Optional Author URL (http(s) only) */
        #[Nullable, StringType]
        public readonly string|Optional $url,

        /** @var string|Optional URL of author icon (http(s) and attachments only) */
        #[Nullable, StringType]
        public readonly string|Optional $icon_url,

        /** @var string|Optional Proxied URL of author icon */
        #[Nullable, StringType]
        public readonly string|Optional $proxy_icon_url,
    ) {}
}
