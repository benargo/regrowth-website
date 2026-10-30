<?php

namespace App\Data\RaidInformation;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class ChapterSummaryData extends Data
{
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly ?string $emoji,
        public readonly int $characterCount,
        public readonly CarbonImmutable $updatedAt,
        public readonly bool $isIntroduction,
    ) {}
}
