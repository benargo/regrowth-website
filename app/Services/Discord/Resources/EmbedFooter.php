<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class EmbedFooter extends Data
{
    public function __construct(
        /** @var string Footer text */
        #[StringType]
        public readonly string $text,

        /** @var string|Optional URL of footer icon (http(s) and attachments only) */
        #[Nullable, StringType]
        public readonly string|Optional $icon_url,

        /** @var string|Optional Proxied URL of footer icon */
        #[Nullable, StringType]
        public readonly string|Optional $proxy_icon_url,
    ) {}
}
