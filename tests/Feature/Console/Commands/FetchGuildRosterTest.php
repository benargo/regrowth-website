<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\FetchGuildRoster;
use App\Jobs\FetchGuildRosters;
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
        RateLimiter::clear('fetch-guild-roster-job:7');
        parent::tearDown();
    }

    #[Test]
    public function it_dispatches_fetch_guild_rosters_without_an_option(): void
    {
        Bus::fake([FetchGuildRoster::class, FetchGuildRosters::class]);

        $this->artisan('fetch:blizzard-roster')
            ->expectsOutput('Guild roster refresh queued for each game version.')
            ->assertSuccessful();

        Bus::assertDispatchedSync(FetchGuildRosters::class);
    }

    #[Test]
    public function it_dispatches_the_roster_job_for_the_given_game_version(): void
    {
        Bus::fake([FetchGuildRoster::class, FetchGuildRosters::class]);

        $this->artisan('fetch:blizzard-roster', ['--game-version' => 7])
            ->expectsOutput('Guild roster refreshed.')
            ->assertSuccessful();

        Bus::assertDispatchedSync(FetchGuildRoster::class, fn (FetchGuildRoster $job) => $job->gameVersionId === 7);
        Bus::assertNotDispatchedSync(FetchGuildRosters::class);
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

    #[Test]
    public function it_warns_when_the_game_version_was_refreshed_recently(): void
    {
        Bus::fake([FetchGuildRoster::class, FetchGuildRosters::class]);
        RateLimiter::hit('fetch-guild-roster-job:7');

        $this->artisan('fetch:blizzard-roster', ['--game-version' => 7])
            ->expectsOutputToContain('The guild roster was refreshed recently.')
            ->assertSuccessful();

        Bus::assertNotDispatchedSync(FetchGuildRoster::class);
    }
}
