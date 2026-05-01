<?php

namespace App\Services\RaidHelper\Resources;

use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class EventRole extends Data
{
    public function __construct(
        /** @var string The name of this role */
        #[StringType]
        public readonly string $name,

        /** @var string The maximum allowed sign-ups for this role */
        #[StringType]
        public readonly string $limit,

        /** @var string The emote id of this role */
        #[StringType]
        public readonly string $emoteId,
    ) {}
}
