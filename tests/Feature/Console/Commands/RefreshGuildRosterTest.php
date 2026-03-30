<?php

namespace Tests\Feature\Console\Commands;

use App\Services\Blizzard\GuildService;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefreshGuildRosterTest extends TestCase
{
    #[Test]
    public function it_refreshes_roster_when_cache_is_empty(): void
    {
        $this->mock(GuildService::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasRosterCache')
                ->once()
                ->andReturn(false);

            $mock->shouldReceive('roster')
                ->once()
                ->andReturn(['members' => []]);
        });

        $this->artisan('app:refresh-guild-roster')
            ->expectsOutput('Guild roster refreshed.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_error_when_roster_is_still_cached(): void
    {
        $this->mock(GuildService::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasRosterCache')
                ->once()
                ->andReturn(true);

            $mock->shouldNotReceive('roster');
        });

        $this->artisan('app:refresh-guild-roster')
            ->expectsOutput('The guild roster was fetched recently. Please wait for the cache to expire.')
            ->assertSuccessful();
    }
}
