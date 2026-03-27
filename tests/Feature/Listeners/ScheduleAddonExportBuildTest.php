<?php

namespace Tests\Feature\Listeners;

use App\Events\AddonSettingsProcessed;
use App\Events\CharacterUpdated;
use App\Jobs\BuildAddonExportFile;
use App\Listeners\ScheduleAddonExportBuild;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ScheduleAddonExportBuildTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // Listener Contract Tests
    // ==========================================

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new ScheduleAddonExportBuild);
    }

    // ==========================================
    // Dispatch Tests
    // ==========================================

    public function test_it_dispatches_job_on_addon_settings_processed_event(): void
    {
        Bus::fake();

        $listener = new ScheduleAddonExportBuild;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertDispatched(BuildAddonExportFile::class);
    }

    public function test_it_dispatches_job_on_character_updated_event(): void
    {
        Bus::fake();

        $listener = new ScheduleAddonExportBuild;
        $listener->handle(new CharacterUpdated);

        Bus::assertDispatched(BuildAddonExportFile::class);
    }

    // ==========================================
    // failed() Handler Tests
    // ==========================================

    public function test_failed_only_logs_for_any_event(): void
    {
        $event = new AddonSettingsProcessed;
        $exception = new \RuntimeException('Something went wrong');

        // Should not throw; just logs
        $listener = new ScheduleAddonExportBuild;
        $listener->failed($event, $exception);

        $this->assertTrue(true); // No exception thrown
    }
}
