<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class EmbedProvider extends Data
{
    public function __construct(
        /** @var string|Optional Name of provider */
        #[Nullable, StringType]
        public readonly string|Optional $name,

        /** @var string|Optional URL of provider */
        #[Nullable, StringType]
        public readonly string|Optional $url,
    ) {}
}
