<?php

namespace Tests\Unit\Providers;

use App\Jobs\SyncDiscordRoles;
use App\Jobs\SyncDiscordUsers;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordServiceProviderTest extends TestCase
{
    #[Test]
    public function it_dispatches_sync_discord_users_when_sync_discord_roles_job_completes(): void
    {
        Bus::fake();

        $job = $this->createStub(Job::class);
        $job->method('resolveName')->willReturn(SyncDiscordRoles::class);

        Queue::after(fn () => null); // ensure listeners are registered

        event(new JobProcessed('sync', $job));

        Bus::assertDispatched(SyncDiscordUsers::class);
    }

    #[Test]
    public function it_does_not_dispatch_sync_discord_users_for_other_jobs(): void
    {
        Bus::fake();

        $job = $this->createStub(Job::class);
        $job->method('resolveName')->willReturn(SyncDiscordRoles::class.'Other');

        event(new JobProcessed('sync', $job));

        Bus::assertNotDispatched(SyncDiscordUsers::class);
    }
}
