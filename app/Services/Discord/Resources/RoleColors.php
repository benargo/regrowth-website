<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;

class RoleColors extends Data
{
    public function __construct(
        /** @var int The primary color for the role */
        #[IntegerType]
        public readonly int $primary_color,

        /** @var int|null The secondary color for the role; makes the role a gradient */
        #[Nullable, IntegerType]
        public readonly ?int $secondary_color = null,

        /** @var int|null The tertiary color for the role; turns the gradient into a holographic style */
        #[Nullable, IntegerType]
        public readonly ?int $tertiary_color = null,
    ) {}
}
