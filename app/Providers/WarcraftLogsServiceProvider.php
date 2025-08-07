<?php

namespace App\Providers;

use App\Services\WarcraftLogs\WarcraftLogsService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class WarcraftLogsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(WarcraftLogsService::class, function (Application $app) {
            return new WarcraftLogsService(config('services.warcraftlogs'));
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
