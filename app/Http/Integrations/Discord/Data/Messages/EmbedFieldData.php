<?php

namespace App\Http\Integrations\Discord\Data\Messages;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class EmbedFieldData extends Data
{
    public function __construct(
        /** @var string Name of the field (max 256 characters) */
        #[StringType, Max(256)]
        public readonly string $name,

        /** @var string Value of the field (max 1024 characters) */
        #[StringType, Max(1024)]
        public readonly string $value,

        /** @var bool|Optional Whether this field should display inline */
        #[BooleanType]
        public readonly bool|Optional $inline,
    ) {}
}
