<?php

namespace App\Http\Integrations\Discord\Data\Messages;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class EmbedFooterData extends Data
{
    public function __construct(
        /** @var string Footer text (max 2048 characters) */
        #[StringType, Max(2048)]
        public readonly string $text,

        /** @var string|Optional URL of footer icon (http(s) and attachments only) */
        #[StringType]
        public readonly string|Optional $iconUrl,

        /** @var string|Optional Proxied URL of footer icon */
        #[StringType]
        public readonly string|Optional $proxyIconUrl,
    ) {}
}
