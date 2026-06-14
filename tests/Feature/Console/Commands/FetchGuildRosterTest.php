<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\FetchGuildRoster;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class FetchGuildRosterTest extends TestCase
{
    protected function tearDown(): void
    {
        RateLimiter::clear('fetch-guild-roster-job');
        parent::tearDown();
    }

    #[Test]
    public function it_dispatches_fetch_guild_roster_synchronously(): void
    {
        Bus::fake([FetchGuildRoster::class]);

        $this->artisan('fetch:blizzard-roster')
            ->expectsOutput('Guild roster refreshed.')
            ->assertSuccessful();

        Bus::assertDispatchedSync(FetchGuildRoster::class);
    }

    #[Test]
    public function it_warns_and_does_not_dispatch_when_rate_limited(): void
    {
        Bus::fake([FetchGuildRoster::class]);
        RateLimiter::hit('fetch-guild-roster-job');

        $interval = $this->createStub(CarbonInterval::class);
        $interval->method('cascade')->willReturnSelf();
        $interval->method('forHumans')->willReturn('15 minutes');
        CarbonInterval::macro('seconds', fn () => $interval);

        $this->artisan('fetch:blizzard-roster')
            ->expectsOutput('The guild roster was refreshed recently. Please wait 15 minutes before refreshing again.')
            ->assertSuccessful();

        Bus::assertNotDispatchedSync(FetchGuildRoster::class);
    }
}
