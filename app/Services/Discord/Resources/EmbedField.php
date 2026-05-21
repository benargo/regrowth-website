<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class EmbedField extends Data
{
    public function __construct(
        /** @var string Name of the field */
        #[StringType]
        public readonly string $name,

        /** @var string Value of the field */
        #[StringType]
        public readonly string $value,

        /** @var bool|Optional Whether this field should display inline */
        #[Nullable, BooleanType]
        public readonly bool|Optional $inline,
    ) {}
}
