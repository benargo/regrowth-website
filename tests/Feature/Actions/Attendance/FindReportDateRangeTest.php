<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\FindReportDateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('raiding')]
class FindReportDateRangeTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    #[Test]
    public function it_finds_the_earliest_and_latest_report_of_the_version(): void
    {
        $gameVersion = $this->makeAttendanceVersion();
        $this->makeReport($gameVersion, '2025-03-10 19:00');
        $this->makeReport($gameVersion, '2025-01-05 19:00');
        $this->makeReport($this->makeAttendanceVersion(), '2024-06-01 19:00');

        $range = FindReportDateRange::run($gameVersion);

        $this->assertSame('2025-01-05 19:00:00', $range->earliest->format('Y-m-d H:i:s'));
        $this->assertSame('2025-03-10 19:00:00', $range->latest->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_returns_null_bounds_without_reports(): void
    {
        $range = FindReportDateRange::run($this->makeAttendanceVersion());

        $this->assertNull($range->earliest);
        $this->assertNull($range->latest);
    }

    #[Test]
    public function it_caches_the_range_per_version_under_the_report_tags(): void
    {
        $gameVersion = $this->makeAttendanceVersion();

        FindReportDateRange::run($gameVersion);

        $this->assertTrue(Cache::tags(['attendance', 'reports'])->has("reports:date_range:{$gameVersion->id}"));
    }
}
