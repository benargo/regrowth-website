<?php

namespace Tests\Unit\Providers;

use App\Http\Integrations\Discord\DiscordConnector;
use App\Jobs\Discord\SyncRoles;
use App\Jobs\Discord\SyncUser;
use App\Models\User;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('discord-integration')]
class DiscordServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_sync_discord_users_when_sync_discord_roles_job_completes(): void
    {
        Bus::fake();

        User::factory()->create();

        $job = $this->createStub(Job::class);
        $job->method('resolveName')->willReturn(SyncRoles::class);

        Queue::after(fn () => null); // ensure listeners are registered

        event(new JobProcessed('sync', $job));

        Bus::assertBatched(fn ($batch) => $batch->jobs->contains(fn ($j) => $j instanceof SyncUser));
    }

    #[Test]
    public function it_does_not_dispatch_sync_discord_users_for_other_jobs(): void
    {
        Bus::fake();

        $job = $this->createStub(Job::class);
        $job->method('resolveName')->willReturn(SyncRoles::class.'Other');

        event(new JobProcessed('sync', $job));

        Bus::assertNothingBatched();
    }

    #[Test]
    #[Group('contract')]
    public function it_binds_the_discord_connector_as_a_singleton(): void
    {
        $connector = $this->app->make(DiscordConnector::class);

        $this->assertInstanceOf(DiscordConnector::class, $connector);
        $this->assertSame($connector, $this->app->make(DiscordConnector::class));
    }

    #[Test]
    #[Group('error-handling')]
    #[TestWith(['services.discord.token'])]
    #[TestWith(['services.discord.server_id'])]
    public function it_refuses_to_build_the_connector_without_required_config(string $key): void
    {
        config([$key => '']);
        $this->app->forgetInstance(DiscordConnector::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($key);

        $this->app->make(DiscordConnector::class);
    }

    #[Test]
    #[Group('contract')]
    public function it_builds_the_connector_from_config(): void
    {
        Saloon::fake([MockResponse::make([], 200)]);

        $probe = new class extends Request
        {
            protected Method $method = Method::GET;

            public function resolveEndpoint(): string
            {
                return '/probe';
            }
        };

        $response = $this->app->make(DiscordConnector::class)->send($probe);
        $appUrl = config('app.url');

        $this->assertSame('Bot '.config('services.discord.token'), $response->getPendingRequest()->headers()->get('Authorization'));
        $this->assertSame("DiscordBot ({$appUrl}, 1.0)", $response->getPendingRequest()->headers()->get('User-Agent'));
    }
}
