<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ConvertReportTimesToAppTimezoneTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_converts_winter_times_by_one_hour(): void
    {
        $report = Report::factory()->create(['start_time' => '2025-01-15 18:00:00', 'end_time' => '2025-01-15 21:30:00']);

        $this->artisan('app:convert-report-times-to-app-timezone --force')->assertExitCode(0);

        $this->assertStoredTimes($report->id, '2025-01-15 19:00:00', '2025-01-15 22:30:00');
    }

    #[Test]
    public function it_converts_summer_times_by_two_hours(): void
    {
        $report = Report::factory()->create(['start_time' => '2025-07-15 17:00:00', 'end_time' => '2025-07-15 22:30:00']);

        $this->artisan('app:convert-report-times-to-app-timezone --force')->assertExitCode(0);

        $this->assertStoredTimes($report->id, '2025-07-15 19:00:00', '2025-07-16 00:30:00');
    }

    #[Test]
    public function it_converts_times_either_side_of_the_clocks_going_back(): void
    {
        $report = Report::factory()->create(['start_time' => '2026-10-25 00:30:00', 'end_time' => '2026-10-25 01:30:00']);

        $this->artisan('app:convert-report-times-to-app-timezone --force')->assertExitCode(0);

        $this->assertStoredTimes($report->id, '2026-10-25 02:30:00', '2026-10-25 02:30:00');
    }

    #[Test]
    public function it_flushes_the_cached_report_date_ranges(): void
    {
        Cache::tags(['reports'])->put('probe', 1);
        Cache::tags(['attendance'])->put('probe', 1);

        $this->artisan('app:convert-report-times-to-app-timezone --force')->assertExitCode(0);

        $this->assertNull(Cache::tags(['reports'])->get('probe'));
        $this->assertNull(Cache::tags(['attendance'])->get('probe'));
    }

    #[Test]
    public function it_reports_how_many_reports_it_converted(): void
    {
        Report::factory()->count(2)->create();

        $this->artisan('app:convert-report-times-to-app-timezone --force')
            ->expectsOutput('Converted 2 report(s).')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_changes_nothing_when_the_confirmation_is_declined(): void
    {
        $report = Report::factory()->create(['start_time' => '2025-01-15 18:00:00', 'end_time' => '2025-01-15 21:30:00']);

        $this->artisan('app:convert-report-times-to-app-timezone')
            ->expectsConfirmation('This shifts every report time and must only run once. Continue?', 'no')
            ->expectsOutput('Aborted.')
            ->assertExitCode(1);

        $this->assertStoredTimes($report->id, '2025-01-15 18:00:00', '2025-01-15 21:30:00');
    }

    private function assertStoredTimes(string $reportId, string $startTime, string $endTime): void
    {
        $this->assertSame($startTime, DB::table('reports')->where('id', $reportId)->value('start_time'));
        $this->assertSame($endTime, DB::table('reports')->where('id', $reportId)->value('end_time'));
    }
}
