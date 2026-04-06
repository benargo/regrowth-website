<?php

namespace App\Providers;

use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\Client;
use App\Services\Blizzard\MediaService;
use App\Services\Blizzard\Region;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class BlizzardServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $config = config('services.blizzard');

        $this->app->bind(Client::class, function (Application $app) use ($config) {
            return new Client(
                clientId: Arr::get($config, 'client_id'),
                clientSecret: Arr::get($config, 'client_secret'),
                region: Region::from(Arr::get($config, 'region', 'eu')),
                locale: Arr::get($config, 'locale'),
            );
        });

        $this->app->singleton(BlizzardService::class, function (Application $app) use ($config) {
            return new BlizzardService(
                $app->make(Client::class),
                $config,
            );
        });

        $this->app->singleton(MediaService::class, function (Application $app) use ($config) {
            return new MediaService(
                Arr::get($config, 'region'),
                $app->make(FilesystemManager::class),
                Arr::get($config, 'filesystem', 'public'),
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
            BlizzardService::class,
            Client::class,
            MediaService::class,
        ];
    }
}
