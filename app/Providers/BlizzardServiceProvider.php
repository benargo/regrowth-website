<?php

namespace App\Providers;

use App\Services\Blizzard\Client;
use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\ServiceProvider;

class BlizzardServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function (Application $app) {
            return Client::fromConfig();
        });

        $this->app->singleton(ItemService::class, function (Application $app) {
            return new ItemService($app->make(Client::class));
        });

        $this->app->singleton(MediaService::class, function (Application $app) {
            return new MediaService(
                $app->make(Client::class),
                $app->make(FilesystemManager::class),
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
}
