<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class EmbedFooter extends Data
{
    public function __construct(
        /** @var string Footer text */
        #[StringType]
        public readonly string $text,

        /** @var string|null URL of footer icon (http(s) and attachments only) */
        #[Nullable, StringType]
        public readonly ?string $icon_url = null,

        /** @var string|null Proxied URL of footer icon */
        #[Nullable, StringType]
        public readonly ?string $proxy_icon_url = null,
    ) {}
}
