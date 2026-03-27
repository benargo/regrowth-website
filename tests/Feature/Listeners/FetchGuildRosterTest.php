<?php

namespace Tests\Feature\Listeners;

use App\Events\AddonSettingsProcessed;
use App\Events\GrmUploadProcessed;
use App\Jobs\FetchGuildRoster as FetchGuildRosterJob;
use App\Listeners\FetchGuildRoster;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class FetchGuildRosterTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================
    // Listener Contract Tests
    // ==========================================

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new FetchGuildRoster);
    }

    // ==========================================
    // Happy Path
    // ==========================================

    public function test_it_dispatches_fetch_guild_roster_on_addon_settings_processed(): void
    {
        Bus::fake();

        $listener = new FetchGuildRoster;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertDispatched(FetchGuildRosterJob::class);
    }

    public function test_it_dispatches_fetch_guild_roster_on_grm_upload_processed(): void
    {
        Bus::fake();

        $listener = new FetchGuildRoster;
        $listener->handle(new GrmUploadProcessed(5, 1, 0, 0, []));

        Bus::assertDispatched(FetchGuildRosterJob::class);
    }

    public function test_it_dispatches_exactly_one_job(): void
    {
        Bus::fake();

        $listener = new FetchGuildRoster;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertDispatchedTimes(FetchGuildRosterJob::class, 1);
    }
}
