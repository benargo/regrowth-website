<?php

namespace App\Http\Integrations\Discord\Data\Messages;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class EmbedAuthorData extends Data
{
    public function __construct(
        /** @var string Name of author (max 256 characters) */
        #[StringType, Max(256)]
        public readonly string $name,

        /** @var string|Optional Author URL (http(s) only) */
        #[StringType]
        public readonly string|Optional $url,

        /** @var string|Optional URL of author icon (http(s) and attachments only) */
        #[StringType]
        public readonly string|Optional $iconUrl,

        /** @var string|Optional Proxied URL of author icon */
        #[StringType]
        public readonly string|Optional $proxyIconUrl,
    ) {}
}
