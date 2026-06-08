<?php

namespace App\Http\Integrations\Blizzard\Data\Shared;

use App\Http\Integrations\Blizzard\Data\Casts\UriCast;
use App\Http\Integrations\Blizzard\Data\Casts\UriTransformer;
use Illuminate\Support\Uri;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

class HrefData extends Data
{
    public function __construct(
        #[WithCast(UriCast::class)]
        #[WithTransformer(UriTransformer::class)]
        public readonly Uri $href,
    ) {}
}
