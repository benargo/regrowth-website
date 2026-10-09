<?php

namespace Tests\Unit\Providers;

use App\Http\Integrations\WarcraftLogs\RateLimitResetCache;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsConnector;
use App\Providers\WarcraftLogsServiceProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
#[Group('platform')]
class WarcraftLogsServiceProviderTest extends TestCase
{
    private const string INTEGRATION_NAMESPACE = 'App\\Http\\Integrations\\WarcraftLogs\\';

    #[Test]
    #[Group('contract')]
    public function it_binds_the_connector_as_a_singleton(): void
    {
        $connector = $this->app->make(WarcraftLogsConnector::class);

        $this->assertInstanceOf(WarcraftLogsConnector::class, $connector);
        $this->assertSame($connector, $this->app->make(WarcraftLogsConnector::class));
    }

    #[Test]
    #[Group('error-handling')]
    #[TestWith(['client_id', null])]
    #[TestWith(['client_id', ''])]
    #[TestWith(['client_secret', null])]
    public function it_refuses_to_build_the_connector_without_a_credential(string $key, ?string $value): void
    {
        config(["services.warcraftlogs.{$key}" => $value]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("services.warcraftlogs.{$key} is not configured.");

        $this->app->make(WarcraftLogsConnector::class);
    }

    #[Test]
    #[Group('contract')]
    public function it_provides_the_connector_and_the_reset_cache(): void
    {
        $provides = (new WarcraftLogsServiceProvider($this->app))->provides();

        $this->assertContains(WarcraftLogsConnector::class, $provides);
        $this->assertContains(RateLimitResetCache::class, $provides);
    }

    #[Test]
    #[Group('contract')]
    public function it_only_provides_classes_from_the_warcraft_logs_integration(): void
    {
        foreach ((new WarcraftLogsServiceProvider($this->app))->provides() as $abstract) {
            $this->assertStringStartsWith(self::INTEGRATION_NAMESPACE, $abstract);
        }
    }
}
