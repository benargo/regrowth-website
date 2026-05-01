<?php

namespace App\Services\RaidHelper\Resources;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class EventClass extends Data
{
    public function __construct(
        /** @var string The name of this class */
        #[StringType]
        public readonly string $name,

        /** @var string The maximum allowed sign-ups for this class */
        #[StringType]
        public readonly string $limit,

        /** @var string The emote id of this class */
        #[StringType]
        public readonly string $emoteId,

        /** @var string The type of this class (primary/default) */
        #[StringType]
        public readonly string $type,

        /** @var array<int, EventSpec> The specs that are applied to this class */
        #[DataCollectionOf(EventSpec::class)]
        public readonly array $specs,
    ) {}
}
