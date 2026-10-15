<?php

namespace App\Http\Integrations\Discord\Data\Messages;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class EmbedProviderData extends Data
{
    public function __construct(
        /** @var string|Optional Name of provider */
        #[StringType]
        public readonly string|Optional $name,

        /** @var string|Optional URL of provider */
        #[StringType]
        public readonly string|Optional $url,
    ) {}
}
