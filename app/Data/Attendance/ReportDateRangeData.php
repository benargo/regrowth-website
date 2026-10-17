<?php

namespace App\Data\Attendance;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * The earliest and latest report start times of a game version.
 */
final class ReportDateRangeData extends Data
{
    public function __construct(
        public readonly ?CarbonImmutable $earliest,
        public readonly ?CarbonImmutable $latest,
    ) {}
}
