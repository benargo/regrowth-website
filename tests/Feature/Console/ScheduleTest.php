<?php

namespace Tests\Feature\Console;

use App\Jobs\Discord\SyncRoles;
use App\Jobs\Discord\SyncUsers;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class ScheduleTest extends TestCase
{
    #[Test]
    public function it_chains_the_discord_role_and_user_sync_hourly(): void
    {
        Bus::fake();

        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => $event->description === 'sync-discord');

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);

        $event->run($this->app);

        Bus::assertChained([SyncRoles::class, SyncUsers::class]);
    }
}
