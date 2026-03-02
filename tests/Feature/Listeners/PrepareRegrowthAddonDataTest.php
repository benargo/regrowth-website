<?php

namespace Tests\Feature\Listeners;

use App\Events\AddonSettingsProcessed;
use App\Events\CharacterUpdated;
use App\Events\GrmUploadProcessed;
use App\Jobs\FetchGuildRoster;
use App\Jobs\FetchWarcraftLogsAttendanceData;
use App\Jobs\ProcessGrmUpload;
use App\Jobs\RegrowthAddon\Export\BuildCouncillors;
use App\Jobs\RegrowthAddon\Export\BuildDataFile;
use App\Jobs\RegrowthAddon\Export\BuildItems;
use App\Jobs\RegrowthAddon\Export\BuildPlayerAttendance;
use App\Jobs\RegrowthAddon\Export\BuildPriorities;
use App\Jobs\SendGrmUploadNotification;
use App\Listeners\PrepareRegrowthAddonData;
use App\Notifications\DiscordNotifiable;
use App\Notifications\GrmUploadFailed;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
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
            FetchWarcraftLogsAttendanceData::class,
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
            FetchWarcraftLogsAttendanceData::class,
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

    public function test_chain_includes_a_fetch_batch_with_roster_job(): void
    {
        Bus::fake();

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertChained([
            Bus::chainedBatch(function (PendingBatch $batch) {
                $jobClasses = $batch->jobs->map(fn ($job) => get_class($job))->toArray();

                return in_array(FetchGuildRoster::class, $jobClasses)
                    && ! in_array(FetchWarcraftLogsAttendanceData::class, $jobClasses);
            }),
            FetchWarcraftLogsAttendanceData::class,
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }

    public function test_chain_includes_fetch_warcraft_logs_attendance_data_as_standalone_step(): void
    {
        Bus::fake();

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            FetchWarcraftLogsAttendanceData::class,
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

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            FetchWarcraftLogsAttendanceData::class,
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
            FetchWarcraftLogsAttendanceData::class,
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
            FetchWarcraftLogsAttendanceData::class,
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }

    public function test_it_dispatches_chain_on_character_updated_event(): void
    {
        Bus::fake();

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new CharacterUpdated);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            FetchWarcraftLogsAttendanceData::class,
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }

    // ==========================================
    // GRM Upload Event Tests
    // ==========================================

    public function test_it_appends_send_grm_upload_notification_job_when_triggered_by_grm_upload(): void
    {
        Bus::fake();

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new GrmUploadProcessed(5, 1, 0, 0, []));

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            FetchWarcraftLogsAttendanceData::class,
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
            new SendGrmUploadNotification(5, 1, 0, 0, []),
        ]);
    }

    public function test_it_does_not_append_notification_job_for_non_grm_events(): void
    {
        Bus::fake();

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new CharacterUpdated);

        Bus::assertChained([
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            FetchWarcraftLogsAttendanceData::class,
            Bus::chainedBatch(fn (PendingBatch $batch) => true),
            new BuildDataFile,
        ]);
    }

    public function test_it_sends_immediate_notification_when_throttled_and_triggered_by_grm_upload(): void
    {
        Bus::fake();

        Cache::put($this->getThrottleCacheKey(), true, now()->addMinutes(10));

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new GrmUploadProcessed(5, 0, 0, 0, []));

        // No chain/batch should be dispatched — only the single notification job
        Bus::assertNothingBatched();
        Bus::assertDispatched(SendGrmUploadNotification::class);
    }

    public function test_it_does_not_send_immediate_notification_when_throttled_and_not_a_grm_upload(): void
    {
        Bus::fake();

        Cache::put($this->getThrottleCacheKey(), true, now()->addMinutes(10));

        $listener = new PrepareRegrowthAddonData;
        $listener->handle(new CharacterUpdated);

        Bus::assertNothingDispatched();
    }

    // ==========================================
    // failed() Handler Tests
    // ==========================================

    public function test_failed_updates_cache_and_notifies_when_triggered_by_grm_upload(): void
    {
        Notification::fake();

        config([
            'services.discord.channels.officer' => '1407688195386114119',
        ]);

        $event = new GrmUploadProcessed(10, 2, 1, 0, []);
        $exception = new \RuntimeException('Serialization failed');

        $listener = new PrepareRegrowthAddonData;
        $listener->failed($event, $exception);

        $progress = Cache::get(ProcessGrmUpload::PROGRESS_CACHE_KEY);
        $this->assertEquals('failed', $progress['status']);
        $this->assertEquals(2, $progress['step']);
        $this->assertStringContainsString('Serialization failed', $progress['message']);

        Notification::assertSentTo(
            new DiscordNotifiable('officer'),
            GrmUploadFailed::class
        );
    }

    public function test_failed_does_not_update_cache_for_non_grm_events(): void
    {
        Notification::fake();

        $event = new AddonSettingsProcessed;
        $exception = new \RuntimeException('Something went wrong');

        $listener = new PrepareRegrowthAddonData;
        $listener->failed($event, $exception);

        $this->assertNull(Cache::get(ProcessGrmUpload::PROGRESS_CACHE_KEY));
        Notification::assertNothingSent();
    }

    /**
     * Retrieve the throttle cache key from the listener using reflection.
     */
    protected function getThrottleCacheKey(): string
    {
        $listener = new PrepareRegrowthAddonData;
        $reflection = new \ReflectionProperty($listener, 'cacheKey');

        return $reflection->getValue($listener);
    }
}
