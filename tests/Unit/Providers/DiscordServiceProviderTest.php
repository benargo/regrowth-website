<?php

namespace Tests\Unit\Providers;

use App\Http\Integrations\Discord\DiscordConnector;
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
