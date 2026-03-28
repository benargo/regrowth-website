<?php

namespace Tests\Feature\Listeners;

use App\Events\AddonSettingsProcessed;
use App\Events\CharacterUpdated;
use App\Jobs\BuildAddonExportFile;
use App\Listeners\ScheduleAddonExportBuild;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScheduleAddonExportBuildTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // Listener Contract Tests
    // ==========================================

    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new ScheduleAddonExportBuild);
    }

    // ==========================================
    // Dispatch Tests
    // ==========================================

    #[Test]
    public function it_dispatches_job_on_addon_settings_processed_event(): void
    {
        Bus::fake();

        $listener = new ScheduleAddonExportBuild;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertDispatched(BuildAddonExportFile::class);
    }

    #[Test]
    public function it_dispatches_job_on_character_updated_event(): void
    {
        Bus::fake();

        $listener = new ScheduleAddonExportBuild;
        $listener->handle(new CharacterUpdated);

        Bus::assertDispatched(BuildAddonExportFile::class);
    }

    // ==========================================
    // failed() Handler Tests
    // ==========================================

    #[Test]
    public function failed_only_logs_for_any_event(): void
    {
        $event = new AddonSettingsProcessed;
        $exception = new \RuntimeException('Something went wrong');

        // Should not throw; just logs
        $listener = new ScheduleAddonExportBuild;
        $listener->failed($event, $exception);

        $this->assertTrue(true); // No exception thrown
    }

    // ==========================================
    // Tags
    // ==========================================

    #[Test]
    public function it_has_correct_tags(): void
    {
        $listener = new ScheduleAddonExportBuild;

        $this->assertSame(['regrowth-addon-export'], $listener->tags());
    }
}
