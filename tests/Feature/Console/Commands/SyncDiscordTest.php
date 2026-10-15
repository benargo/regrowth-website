<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\Discord\SyncRoles;
use App\Jobs\Discord\SyncUser;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class SyncDiscordTest extends TestCase
{
    #[Test]
    public function it_exits_successfully(): void
    {
        Bus::fake([SyncRoles::class, SyncUser::class]);

        $this->artisan('sync:discord')
            ->assertSuccessful();
    }

    #[Test]
    public function it_dispatches_sync_discord_roles(): void
    {
        Bus::fake([SyncRoles::class, SyncUser::class]);

        $this->artisan('sync:discord');

        Bus::assertDispatched(SyncRoles::class);
    }

    #[Test]
    public function it_does_not_dispatch_sync_discord_users(): void
    {
        Bus::fake([SyncRoles::class, SyncUser::class]);

        $this->artisan('sync:discord');

        Bus::assertNotDispatched(SyncUser::class);
    }
}
