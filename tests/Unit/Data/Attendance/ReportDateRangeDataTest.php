<?php

namespace Tests\Unit\Data\Attendance;

use App\Data\Attendance\ReportDateRangeData;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ReportDateRangeDataTest extends TestCase
{
    #[Test]
    public function it_exposes_the_earliest_and_latest_start_times(): void
    {
        $earliest = CarbonImmutable::parse('2025-01-01 19:00', 'UTC');
        $latest = CarbonImmutable::parse('2025-03-01 19:00', 'UTC');

        $range = new ReportDateRangeData(earliest: $earliest, latest: $latest);

        $this->assertSame($earliest, $range->earliest);
        $this->assertSame($latest, $range->latest);
    }

    #[Test]
    public function it_allows_both_bounds_to_be_null(): void
    {
        $range = new ReportDateRangeData(earliest: null, latest: null);

        $this->assertNull($range->earliest);
        $this->assertNull($range->latest);
    }
}
