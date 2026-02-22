<?php

namespace App\Providers;

use App\Services\WarcraftLogs\Attendance;
use App\Services\WarcraftLogs\AuthenticationHandler;
use App\Services\WarcraftLogs\Guild;
use App\Services\WarcraftLogs\GuildTags;
use App\Services\WarcraftLogs\Reports;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

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
            AuthenticationHandler::class,
        ];
    }
}
