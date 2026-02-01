<?php

namespace App\Providers;

use App\Services\WarcraftLogs\AuthenticationHandler;
use App\Services\WarcraftLogs\GuildService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class WarcraftLogsServiceProvider extends ServiceProvider
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
                $config['client_secret'],
                $config['token_url']
            );
        });

        $this->app->singleton(GuildService::class, function (Application $app) {
            return new GuildService(config('services.warcraftlogs'));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
