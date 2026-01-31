<?php

namespace App\Providers;

use App\Services\Blizzard\Client;
use App\Services\Blizzard\GuildService;
use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
use App\Services\Blizzard\PlayableClassService;
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
        $this->app->bind(Client::class, function (Application $app) {
            return Client::fromConfig();
        });

        $this->app->singleton(GuildService::class, function (Application $app) {
            return new GuildService($app->make(Client::class));
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

        $this->app->singleton(PlayableClassService::class, function (Application $app) {
            return new PlayableClassService($app->make(Client::class));
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
            Client::class,
            GuildService::class,
            ItemService::class,
            MediaService::class,
            PlayableClassService::class,
        ];
    }
}
