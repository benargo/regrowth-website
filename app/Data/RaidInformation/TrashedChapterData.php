<?php

namespace App\Data\RaidInformation;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class TrashedChapterData extends Data
{
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly ?string $emoji,
        public readonly CarbonImmutable $trashedAt,
        public readonly CarbonImmutable $purgesAt,
        public readonly int $daysRemaining,
    ) {}
}
