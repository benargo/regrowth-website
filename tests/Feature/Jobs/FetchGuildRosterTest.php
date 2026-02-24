<?php

namespace Tests\Feature\Jobs;

use App\Events\GuildRosterFetched;
use App\Jobs\FetchGuildRoster;
use App\Services\Blizzard\GuildService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class FetchGuildRosterTest extends TestCase
{
    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new FetchGuildRoster);
    }

    public function test_it_uses_batchable(): void
    {
        $this->assertContains(Batchable::class, class_uses_recursive(FetchGuildRoster::class));
    }

    public function test_it_calls_roster_on_guild_service(): void
    {
        $guildService = Mockery::mock(GuildService::class);
        $guildService->shouldReceive('roster')->once()->andReturn(['members' => []]);

        $job = new FetchGuildRoster;
        $job->handle($guildService);
    }

    public function test_it_dispatches_guild_roster_fetched_event(): void
    {
        Event::fake([GuildRosterFetched::class]);

        $roster = [
            'members' => [
                ['character' => ['id' => 1, 'name' => 'TestChar', 'level' => 80], 'rank' => 2],
            ],
        ];

        $guildService = Mockery::mock(GuildService::class);
        $guildService->shouldReceive('roster')->once()->andReturnUsing(function () use ($roster) {
            GuildRosterFetched::dispatch($roster);

            return $roster;
        });

        $job = new FetchGuildRoster;
        $job->handle($guildService);

        Event::assertDispatched(GuildRosterFetched::class, function (GuildRosterFetched $event) use ($roster) {
            return $event->roster === $roster;
        });
    }
}
