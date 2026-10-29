<?php

namespace Tests\Feature\Console;

use App\Jobs\Discord\AttachAvatarToUser;
use App\Jobs\Discord\RefreshUserAvatars;
use App\Jobs\Discord\SyncChannels;
use App\Jobs\Discord\SyncRoles;
use App\Jobs\Discord\SyncUsers;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class ScheduleTest extends TestCase
{
    #[Test]
    public function it_chains_the_discord_channel_role_and_user_sync_hourly(): void
    {
        Bus::fake();

        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => $event->description === 'sync-discord');

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);

        $event->run($this->app);

        Bus::assertChained([SyncChannels::class, SyncRoles::class, SyncUsers::class]);
    }

    #[Test]
    public function it_refreshes_user_avatars_daily(): void
    {
        Queue::fake([RefreshUserAvatars::class, AttachAvatarToUser::class]);

        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => $event->description === 'refresh-user-avatars');

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);

        $event->run($this->app);

        Queue::assertPushed(RefreshUserAvatars::class);
    }
}
