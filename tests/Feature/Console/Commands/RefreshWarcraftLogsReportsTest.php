<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\FetchGuildRoster;
use App\Jobs\FetchWarcraftLogsAttendanceData;
use App\Jobs\FetchWarcraftLogsReportsByGuildTag;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use Carbon\Carbon;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefreshWarcraftLogsReportsTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // Happy Path
    // ==========================================

    #[Test]
    public function it_dispatches_a_batch_with_all_required_job_types(): void
    {
        Bus::fake();

        $tag1 = GuildTag::factory()->countsAttendance()->create();
        $tag2 = GuildTag::factory()->countsAttendance()->create();

        $this->artisan('app:refresh-warcraft-logs-reports')
            ->assertSuccessful();

        Bus::assertBatched(function (PendingBatch $batch) use ($tag1, $tag2) {
            return $batch->jobs->contains(fn ($job) => $job instanceof FetchGuildRoster)
                && $batch->jobs->contains(fn ($job) => $job instanceof FetchWarcraftLogsAttendanceData)
                && $batch->jobs->contains(fn ($job) => $job instanceof FetchWarcraftLogsReportsByGuildTag && $job->guildTag->is($tag1) && $job->since === null)
                && $batch->jobs->contains(fn ($job) => $job instanceof FetchWarcraftLogsReportsByGuildTag && $job->guildTag->is($tag2) && $job->since === null);
        });
    }

    #[Test]
    public function it_only_includes_guild_tags_that_count_attendance(): void
    {
        Bus::fake();

        $attendanceTag = GuildTag::factory()->countsAttendance()->create();
        GuildTag::factory()->doesNotCountAttendance()->create();

        $this->artisan('app:refresh-warcraft-logs-reports')
            ->assertSuccessful();

        Bus::assertBatched(function (PendingBatch $batch) use ($attendanceTag) {
            $fetchReportJobs = $batch->jobs->filter(
                fn ($job) => $job instanceof FetchWarcraftLogsReportsByGuildTag
            );

            return $fetchReportJobs->count() === 1
                && $fetchReportJobs->first()->guildTag->is($attendanceTag);
        });
    }

    #[Test]
    public function it_always_includes_fetch_guild_roster_and_attendance_data_jobs(): void
    {
        Bus::fake();

        $this->artisan('app:refresh-warcraft-logs-reports')
            ->assertSuccessful();

        Bus::assertBatched(function (PendingBatch $batch) {
            return $batch->jobs->contains(fn ($job) => $job instanceof FetchGuildRoster)
                && $batch->jobs->contains(fn ($job) => $job instanceof FetchWarcraftLogsAttendanceData);
        });
    }

    // ==========================================
    // --latest Option
    // ==========================================

    #[Test]
    public function it_passes_null_since_when_latest_flag_is_absent(): void
    {
        Bus::fake();

        GuildTag::factory()->countsAttendance()->create();
        Report::factory()->create(['end_time' => now()->subHour()]);

        $this->artisan('app:refresh-warcraft-logs-reports')
            ->assertSuccessful();

        Bus::assertBatched(function (PendingBatch $batch) {
            $fetchReportJob = $batch->jobs->first(
                fn ($job) => $job instanceof FetchWarcraftLogsReportsByGuildTag
            );

            return $fetchReportJob->since === null;
        });
    }

    #[Test]
    public function it_uses_the_latest_report_end_time_plus_one_second_as_since(): void
    {
        Bus::fake();

        GuildTag::factory()->countsAttendance()->create();

        $endTime = Carbon::parse('2025-06-01 20:00:00');
        Report::factory()->create(['end_time' => $endTime]);

        $this->artisan('app:refresh-warcraft-logs-reports', ['--latest' => true])
            ->assertSuccessful();

        $expectedSince = $endTime->copy()->addSecond();

        Bus::assertBatched(function (PendingBatch $batch) use ($expectedSince) {
            $fetchReportJob = $batch->jobs->first(
                fn ($job) => $job instanceof FetchWarcraftLogsReportsByGuildTag
            );

            return $fetchReportJob->since->eq($expectedSince);
        });
    }

    #[Test]
    public function it_passes_null_since_when_latest_flag_is_set_but_no_reports_exist(): void
    {
        Bus::fake();

        GuildTag::factory()->countsAttendance()->create();

        $this->artisan('app:refresh-warcraft-logs-reports', ['--latest' => true])
            ->assertSuccessful();

        Bus::assertBatched(function (PendingBatch $batch) {
            $fetchReportJob = $batch->jobs->first(
                fn ($job) => $job instanceof FetchWarcraftLogsReportsByGuildTag
            );

            return $fetchReportJob->since === null;
        });
    }

    #[Test]
    public function it_uses_the_most_recently_created_report_when_multiple_reports_exist(): void
    {
        Bus::fake();

        GuildTag::factory()->countsAttendance()->create();

        $olderEndTime = Carbon::parse('2025-05-01 18:00:00');
        $newerEndTime = Carbon::parse('2025-06-15 22:00:00');

        Report::factory()->create(['end_time' => $olderEndTime, 'created_at' => now()->subMinute()]);
        Report::factory()->create(['end_time' => $newerEndTime, 'created_at' => now()]);

        $this->artisan('app:refresh-warcraft-logs-reports', ['--latest' => true])
            ->assertSuccessful();

        $expectedSince = $newerEndTime->copy()->addSecond();

        Bus::assertBatched(function (PendingBatch $batch) use ($expectedSince) {
            $fetchReportJob = $batch->jobs->first(
                fn ($job) => $job instanceof FetchWarcraftLogsReportsByGuildTag
            );

            return $fetchReportJob->since->eq($expectedSince);
        });
    }

    // ==========================================
    // --all Option
    // ==========================================

    #[Test]
    public function it_includes_all_guild_tags_when_all_flag_is_set(): void
    {
        Bus::fake();

        $attendanceTag = GuildTag::factory()->countsAttendance()->create();
        $nonAttendanceTag = GuildTag::factory()->doesNotCountAttendance()->create();

        $this->artisan('app:refresh-warcraft-logs-reports', ['--all' => true])
            ->assertSuccessful();

        Bus::assertBatched(function (PendingBatch $batch) use ($attendanceTag, $nonAttendanceTag) {
            $fetchReportJobs = $batch->jobs->filter(
                fn ($job) => $job instanceof FetchWarcraftLogsReportsByGuildTag
            );

            return $fetchReportJobs->count() === 2
                && $fetchReportJobs->contains(fn ($job) => $job->guildTag->is($attendanceTag))
                && $fetchReportJobs->contains(fn ($job) => $job->guildTag->is($nonAttendanceTag));
        });
    }

    #[Test]
    public function it_only_includes_attendance_guild_tags_when_all_flag_is_absent(): void
    {
        Bus::fake();

        $attendanceTag = GuildTag::factory()->countsAttendance()->create();
        $nonAttendanceTag = GuildTag::factory()->doesNotCountAttendance()->create();

        $this->artisan('app:refresh-warcraft-logs-reports')
            ->assertSuccessful();

        Bus::assertBatched(function (PendingBatch $batch) use ($attendanceTag, $nonAttendanceTag) {
            $fetchReportJobs = $batch->jobs->filter(
                fn ($job) => $job instanceof FetchWarcraftLogsReportsByGuildTag
            );

            return $fetchReportJobs->count() === 1
                && $fetchReportJobs->contains(fn ($job) => $job->guildTag->is($attendanceTag))
                && ! $fetchReportJobs->contains(fn ($job) => $job->guildTag->is($nonAttendanceTag));
        });
    }

    // ==========================================
    // Batch Count
    // ==========================================

    #[Test]
    public function it_dispatches_exactly_one_batch(): void
    {
        Bus::fake();

        GuildTag::factory()->countsAttendance()->count(3)->create();

        $this->artisan('app:refresh-warcraft-logs-reports')
            ->assertSuccessful();

        Bus::assertBatchCount(1);
    }

    #[Test]
    public function it_dispatches_the_correct_total_number_of_jobs(): void
    {
        Bus::fake();

        GuildTag::factory()->countsAttendance()->count(3)->create();

        $this->artisan('app:refresh-warcraft-logs-reports')
            ->assertSuccessful();

        // 3 FetchWarcraftLogsReportsByGuildTag + 1 FetchGuildRoster + 1 FetchWarcraftLogsAttendanceData
        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 5);
    }
}
