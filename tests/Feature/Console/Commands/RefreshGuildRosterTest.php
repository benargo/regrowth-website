<?php

namespace Tests\Feature\Console\Commands;

use App\Services\Blizzard\GuildService;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefreshGuildRosterTest extends TestCase
{
    #[Test]
    public function it_calls_guild_service_fresh_then_roster(): void
    {
        $this->mock(GuildService::class, function (MockInterface $mock) {
            $mock->shouldReceive('fresh')
                ->once()
                ->andReturnSelf();

            $mock->shouldReceive('roster')
                ->once()
                ->andReturn(['members' => []]);
        });

        $this->artisan('app:refresh-guild-roster')
            ->assertSuccessful();
    }
}
