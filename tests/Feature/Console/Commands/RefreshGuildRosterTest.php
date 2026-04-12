<?php

namespace Tests\Feature\Console\Commands;

use App\Services\Blizzard\BlizzardService;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefreshGuildRosterTest extends TestCase
{
    #[Test]
    public function it_refreshes_roster_when_cache_is_empty(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cacheKey')
                ->with('getGuildRoster')
                ->andReturn('blizzard.getGuildRoster.test');

            $mock->shouldReceive('getGuildRoster')
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
        $cacheKey = 'blizzard.getGuildRoster.test';

        $this->mock(BlizzardService::class, function (MockInterface $mock) use ($cacheKey) {
            $mock->shouldReceive('cacheKey')
                ->with('getGuildRoster')
                ->andReturn($cacheKey);

            $mock->shouldNotReceive('getGuildRoster');
        });

        Cache::tags(['blizzard', 'blizzard-api-response'])->put($cacheKey, ['members' => []], 900);

        $this->artisan('app:refresh-guild-roster')
            ->expectsOutput('The guild roster was fetched recently. Please wait for the cache to expire.')
            ->assertSuccessful();

        Cache::tags(['blizzard', 'blizzard-api-response'])->forget($cacheKey);
    }
}
