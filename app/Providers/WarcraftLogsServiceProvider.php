<?php

namespace App\Providers;

use App\Http\Integrations\WarcraftLogs\Middleware\MonitorRateLimit;
use App\Http\Integrations\WarcraftLogs\RateLimitResetCache;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsConnector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;

class WarcraftLogsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(RateLimitResetCache::class, function (): RateLimitResetCache {
            return new RateLimitResetCache(
                Cache::store()->tags(['warcraftlogs', 'warcraftlogs-rate-limit']),
            );
        });

        $this->app->singleton(WarcraftLogsConnector::class, function (Application $app): WarcraftLogsConnector {
            $config = config('services.warcraftlogs');

            return new WarcraftLogsConnector(
                clientId: data_get($config, 'client_id') ?: throw new RuntimeException('services.warcraftlogs.client_id is not configured.'),
                clientSecret: data_get($config, 'client_secret') ?: throw new RuntimeException('services.warcraftlogs.client_secret is not configured.'),
                rateLimitReset: $app->make(RateLimitResetCache::class),
                monitorRateLimit: $app->make(MonitorRateLimit::class),
                store: new LaravelCacheStore(
                    Cache::store()->tags(['warcraftlogs', 'warcraftlogs-rate-limit'])
                ),
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [
            RateLimitResetCache::class,
            WarcraftLogsConnector::class,
        ];
    }
}
