<?php

namespace Tests\Feature\Commands;

use App\Jobs\FetchGuildRoster;
use App\Jobs\FetchWarcraftLogsAttendanceData;
use App\Jobs\FetchWarcraftLogsReportsByGuildTag;
use App\Jobs\RegrowthAddon\Export\BuildCouncillors;
use App\Jobs\RegrowthAddon\Export\BuildDataFile;
use App\Jobs\RegrowthAddon\Export\BuildItems;
use App\Jobs\RegrowthAddon\Export\BuildPlayerAttendance;
use App\Jobs\RegrowthAddon\Export\BuildPriorities;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PrepareRegrowthAddonDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_a_chain(): void
    {
        Bus::fake();

        $this->artisan('app:prep-addon-data')->assertExitCode(0);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            FetchGuildRoster::class,
            FetchWarcraftLogsAttendanceData::class,
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }

    public function test_fetch_batch_contains_one_job_per_attendance_guild_tag(): void
    {
        GuildTag::factory()->countsAttendance()->count(2)->create();
        GuildTag::factory()->doesNotCountAttendance()->create();

        Bus::fake();

        $this->artisan('app:prep-addon-data')->assertExitCode(0);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => $batch->jobs->count() === 2),
            FetchGuildRoster::class,
            FetchWarcraftLogsAttendanceData::class,
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }

    public function test_fetch_batch_contains_fetch_reports_by_guild_tag_jobs(): void
    {
        GuildTag::factory()->countsAttendance()->count(2)->create();

        Bus::fake();

        $this->artisan('app:prep-addon-data')->assertExitCode(0);

        Bus::assertChained([
            Bus::chainedBatch(function (PendingBatch $batch) {
                return $batch->jobs->every(fn ($job) => $job instanceof FetchWarcraftLogsReportsByGuildTag);
            }),
            FetchGuildRoster::class,
            FetchWarcraftLogsAttendanceData::class,
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }

    public function test_chain_includes_a_build_batch_with_all_four_export_jobs(): void
    {
        Bus::fake();

        $this->artisan('app:prep-addon-data')->assertExitCode(0);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            FetchGuildRoster::class,
            FetchWarcraftLogsAttendanceData::class,
            Bus::chainedBatch(function (PendingBatch $batch) {
                $jobClasses = $batch->jobs->map(fn ($job) => get_class($job))->toArray();

                return in_array(BuildPriorities::class, $jobClasses)
                    && in_array(BuildItems::class, $jobClasses)
                    && in_array(BuildPlayerAttendance::class, $jobClasses)
                    && in_array(BuildCouncillors::class, $jobClasses);
            }),
            new BuildDataFile,
        ]);
    }

    public function test_build_batch_contains_exactly_four_jobs(): void
    {
        Bus::fake();

        $this->artisan('app:prep-addon-data')->assertExitCode(0);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            FetchGuildRoster::class,
            FetchWarcraftLogsAttendanceData::class,
            Bus::chainedBatch(fn (PendingBatch $batch) => $batch->jobs->count() === 4),
            new BuildDataFile,
        ]);
    }

    public function test_chain_ends_with_build_data_file_job(): void
    {
        Bus::fake();

        $this->artisan('app:prep-addon-data')->assertExitCode(0);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            FetchGuildRoster::class,
            FetchWarcraftLogsAttendanceData::class,
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }
}
