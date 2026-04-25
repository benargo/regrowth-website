<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class EmbedAuthor extends Data
{
    public function __construct(
        /** @var string Name of author */
        #[StringType]
        public readonly string $name,

        /** @var string|null Author URL (http(s) only) */
        #[Nullable, StringType]
        public readonly ?string $url = null,

        /** @var string|null URL of author icon (http(s) and attachments only) */
        #[Nullable, StringType]
        public readonly ?string $icon_url = null,

        /** @var string|null Proxied URL of author icon */
        #[Nullable, StringType]
        public readonly ?string $proxy_icon_url = null,
    ) {}
}
