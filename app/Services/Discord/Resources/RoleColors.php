<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class RoleColors extends Data
{
    public function __construct(
        /** @var int The primary color for the role */
        #[IntegerType]
        public readonly int $primary_color,

        /** @var int|Optional The secondary color for the role; makes the role a gradient */
        #[Nullable, IntegerType]
        public readonly int|Optional $secondary_color,

        /** @var int|Optional The tertiary color for the role; turns the gradient into a holographic style */
        #[Nullable, IntegerType]
        public readonly int|Optional $tertiary_color,
    ) {}
}
