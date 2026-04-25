<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class EmbedProvider extends Data
{
    public function __construct(
        /** @var string|null Name of provider */
        #[Nullable, StringType]
        public readonly ?string $name = null,

        /** @var string|null URL of provider */
        #[Nullable, StringType]
        public readonly ?string $url = null,
    ) {}
}
