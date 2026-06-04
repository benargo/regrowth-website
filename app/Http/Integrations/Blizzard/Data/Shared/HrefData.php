<?php

namespace App\Http\Integrations\Blizzard\Data\Shared;

use Spatie\LaravelData\Data;

class HrefData extends Data
{
    public function __construct(
        public readonly string $href,
    ) {}
}
