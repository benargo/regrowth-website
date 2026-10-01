<?php

namespace Tests\Feature\Listeners;

use App\Events\AddonSettingsProcessed;
use App\Jobs\FetchGuildRoster as FetchGuildRosterJob;
use App\Jobs\FetchGuildRosters;
use App\Listeners\FetchGuildRoster;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class FetchGuildRosterTest extends TestCase
{
    use RefreshDatabase;

    #[Group('contract')]
    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new FetchGuildRoster);
    }

    #[Group('contract')]
    #[Test]
    public function it_has_correct_tags(): void
    {
        $listener = new FetchGuildRoster;

        $this->assertSame(['blizzard'], $listener->tags());
    }

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_fetch_guild_rosters_for_addon_settings_events(): void
    {
        Bus::fake();

        $listener = new FetchGuildRoster;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertDispatched(FetchGuildRosters::class);
        Bus::assertNotDispatched(FetchGuildRosterJob::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_exactly_one_job(): void
    {
        Bus::fake();

        $listener = new FetchGuildRoster;
        $listener->handle(new AddonSettingsProcessed);

        Bus::assertDispatchedTimes(FetchGuildRosters::class, 1);
    }
}
