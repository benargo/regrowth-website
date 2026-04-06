<?php

namespace Tests\Feature\Jobs;

use App\Jobs\FetchGuildRoster;
use App\Services\Blizzard\BlizzardService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetchGuildRosterTest extends TestCase
{
    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new FetchGuildRoster);
    }

    #[Test]
    public function it_uses_batchable(): void
    {
        $this->assertContains(Batchable::class, class_uses_recursive(FetchGuildRoster::class));
    }

    #[Test]
    public function it_calls_get_guild_roster_on_blizzard_service(): void
    {
        $blizzard = Mockery::mock(BlizzardService::class);
        $blizzard->shouldReceive('getGuildRoster')->once()->andReturn(['members' => []]);

        $job = new FetchGuildRoster;
        $job->handle($blizzard);
    }
}
