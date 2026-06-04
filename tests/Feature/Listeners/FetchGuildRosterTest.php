<?php

namespace Tests\Feature\Listeners;

use App\Events\AddonSettingsProcessed;
use App\Events\GrmUploadProcessed;
use App\Jobs\FetchGuildRoster as FetchGuildRosterJob;
use App\Listeners\FetchGuildRoster;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetchGuildRosterTest extends TestCase
{
    use RefreshDatabase;

    #[Group('listener-contract')]
    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new FetchGuildRoster);
    }

    #[Group('listener-contract')]
    #[Test]
    public function it_has_correct_tags(): void
    {
        $listener = new FetchGuildRoster;

        $this->assertSame(['blizzard'], $listener->tags());
    }

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_fetch_guild_roster_on_addon_settings_processed(): void
    {
        Bus::fake();

        $listener = new FetchGuildRoster;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertDispatched(FetchGuildRosterJob::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_fetch_guild_roster_on_grm_upload_processed(): void
    {
        Bus::fake();

        $listener = new FetchGuildRoster;
        $listener->handle(new GrmUploadProcessed(5, 1, 0, 0, []));

        Bus::assertDispatched(FetchGuildRosterJob::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_exactly_one_job(): void
    {
        Bus::fake();

        $listener = new FetchGuildRoster;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertDispatchedTimes(FetchGuildRosterJob::class, 1);
    }
}
