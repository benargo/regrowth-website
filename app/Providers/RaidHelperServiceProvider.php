<?php

namespace App\Providers;

use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class RaidHelperServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Saloon connector for the Raid Helper API.
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
            RaidHelperConnector::class,
        ];
    }
}
