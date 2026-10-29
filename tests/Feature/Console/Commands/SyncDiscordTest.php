<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\Discord\SyncRoles;
use App\Jobs\Discord\SyncUser;
use App\Jobs\Discord\SyncUsers;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('discord-integration')]
class SyncDiscordTest extends TestCase
{
    #[Test]
    public function it_dispatches_the_role_sync_followed_by_the_user_sync(): void
    {
        Bus::fake();

        $this->artisan('sync:discord')->assertSuccessful();

        Bus::assertChained([SyncRoles::class, SyncUsers::class]);
    }

    #[Test]
    public function it_does_not_dispatch_single_user_syncs(): void
    {
        Bus::fake();

        $this->artisan('sync:discord');

        Bus::assertNotDispatched(SyncUser::class);
    }
}
