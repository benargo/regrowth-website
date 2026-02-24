<?php

namespace Tests\Feature\Listeners;

use App\Events\AddonSettingsProcessed;
use App\Jobs\FetchGuildRoster;
use App\Jobs\FetchWarcraftLogsAttendanceData;
use App\Jobs\RegrowthAddon\Export\BuildCouncillors;
use App\Jobs\RegrowthAddon\Export\BuildDataFile;
use App\Jobs\RegrowthAddon\Export\BuildItems;
use App\Jobs\RegrowthAddon\Export\BuildPlayerAttendance;
use App\Jobs\RegrowthAddon\Export\BuildPriorities;
use App\Listeners\PrepareRegrowthAddonData;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PrepareRegrowthAddonDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    // ==========================================
    // Listener Contract Tests
    // ==========================================

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new PrepareRegrowthAddonData);
    }

    // ==========================================
    // Throttle Tests
    // ==========================================

    public function test_it_dispatches_chain_when_not_throttled(): void
    {
        Bus::fake();

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }

    public function test_it_does_not_dispatch_when_within_ten_minute_throttle(): void
    {
        Bus::fake();

        Cache::put('regrowth-addon.export.last-dispatched', true, now()->addMinutes(10));

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertNothingDispatched();
    }

    public function test_it_dispatches_again_after_throttle_expires(): void
    {
        Bus::fake();

        $listener = new PrepareRegrowthAddonData;

        // First call — dispatches and sets throttle
        $listener->handle(new AddonSettingsProcessed);

        // Simulate throttle expiry
        Cache::forget('regrowth-addon.export.last-dispatched');

        // Second call after expiry — dispatches again
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }

    public function test_second_call_within_throttle_window_is_skipped(): void
    {
        Bus::fake();

        $listener = new PrepareRegrowthAddonData;

        $listener->handle(new AddonSettingsProcessed);

        // Reset assertions, then verify the second call dispatches nothing
        Bus::fake();
        Cache::put('regrowth-addon.export.last-dispatched', true, now()->addMinutes(10));

        $listener->handle(new AddonSettingsProcessed);

        Bus::assertNothingDispatched();
    }

    // ==========================================
    // Chain Contents Tests
    // ==========================================

    public function test_chain_includes_a_fetch_batch_with_roster_and_attendance_jobs(): void
    {
        Bus::fake();

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertChained([
            Bus::chainedBatch(function (PendingBatch $batch) {
                $jobClasses = $batch->jobs->map(fn ($job) => get_class($job))->toArray();

                return in_array(FetchGuildRoster::class, $jobClasses)
                    && in_array(FetchWarcraftLogsAttendanceData::class, $jobClasses);
            }),
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }

    public function test_chain_includes_a_build_batch_with_all_four_export_jobs(): void
    {
        Bus::fake();

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
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

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            Bus::chainedBatch(fn (PendingBatch $batch) => $batch->jobs->count() === 4),
            new BuildDataFile,
        ]);
    }

    public function test_chain_ends_with_build_data_file_job(): void
    {
        Bus::fake();

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }

    // ==========================================
    // Event Type Tests
    // ==========================================

    public function test_it_dispatches_chain_on_addon_settings_processed_event(): void
    {
        Bus::fake();

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }
}
