<?php

namespace App\Data\Navigation;

use Spatie\LaravelData\Data;

final class SignupLineData extends Data
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $icon = null,
    ) {}
}
