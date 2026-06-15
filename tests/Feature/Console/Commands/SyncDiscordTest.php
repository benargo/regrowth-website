<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\SyncDiscordRoles;
use App\Jobs\SyncDiscordUsers;
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
        Bus::fake();

        $this->artisan('sync:discord')
            ->assertSuccessful();
    }

    #[Test]
    public function it_dispatches_sync_discord_roles(): void
    {
        Bus::fake();

        $this->artisan('sync:discord');

        Bus::assertDispatched(SyncDiscordRoles::class);
    }

    #[Test]
    public function it_does_not_dispatch_sync_discord_users(): void
    {
        Bus::fake();

        $this->artisan('sync:discord');

        Bus::assertNotDispatched(SyncDiscordUsers::class);
    }
}
