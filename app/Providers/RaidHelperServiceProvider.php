<?php

namespace App\Providers;

use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Services\RaidHelper\RaidHelper;
use App\Services\RaidHelper\RaidHelperClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class RaidHelperServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // API client
        $this->app->singleton(RaidHelperClient::class, function (Application $app) {
            return new RaidHelperClient(config('services.raidhelper.token'));
        });

        // Main RaidHelper service
        $this->app->singleton(RaidHelper::class, function (Application $app) {
            return new RaidHelper($app->make(RaidHelperClient::class), config('services.raidhelper'));
        });

        // Saloon connector (runs alongside the legacy service during migration)
        $this->app->singleton(RaidHelperConnector::class, function (Application $app) {
            return new RaidHelperConnector(
                token: config('services.raidhelper.token'),
                serverId: config('services.raidhelper.server_id'),
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            RaidHelperClient::class,
            RaidHelper::class,
            RaidHelperConnector::class,
        ];
    }
}
