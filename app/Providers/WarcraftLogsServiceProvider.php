<?php

namespace App\Providers;

use App\Http\Integrations\WarcraftLogs\Middleware\MonitorRateLimit;
use App\Http\Integrations\WarcraftLogs\RateLimitResetCache;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsConnector;
use App\Services\WarcraftLogs\Attendance;
use App\Services\WarcraftLogs\AuthenticationHandler;
use App\Services\WarcraftLogs\Guild;
use App\Services\WarcraftLogs\GuildTags;
use App\Services\WarcraftLogs\Reports;
use App\Services\WarcraftLogs\WorldData;
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
        $this->app->singleton(AuthenticationHandler::class, function (Application $app) {
            $config = config('services.warcraftlogs');

            return new AuthenticationHandler(
                $config['client_id'],
                $config['client_secret']
            );
        });

        $this->app->singleton(Attendance::class, function (Application $app) {
            return new Attendance(config('services.warcraftlogs'), $app->make(AuthenticationHandler::class));
        });

        $this->app->singleton(Guild::class, function (Application $app) {
            return new Guild(config('services.warcraftlogs'), $app->make(AuthenticationHandler::class));
        });

        $this->app->singleton(GuildTags::class, function (Application $app) {
            return new GuildTags(config('services.warcraftlogs'), $app->make(AuthenticationHandler::class));
        });

        $this->app->singleton(Reports::class, function (Application $app) {
            return new Reports(config('services.warcraftlogs'), $app->make(AuthenticationHandler::class));
        });

        $this->app->singleton(WorldData::class, function (Application $app) {
            return new WorldData(config('services.warcraftlogs'), $app->make(AuthenticationHandler::class));
        });

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
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            Guild::class,
            GuildTags::class,
            Attendance::class,
            Reports::class,
            WorldData::class,
            AuthenticationHandler::class,
            RateLimitResetCache::class,
            WarcraftLogsConnector::class,
        ];
    }
}
