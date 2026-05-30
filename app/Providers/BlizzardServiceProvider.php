<?php

namespace App\Providers;

use App\Facades\Blizzard as BlizzardFacade;
use App\Facades\BlizzardRenderPath;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\GameVersion;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Support\MirrorPathResolver;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\Client;
use App\Services\Blizzard\MediaService;
use App\Services\Blizzard\Region as LegacyRegion;
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

        /**
         * TODO: Remove when refactor is complete.
         */
        $this->app->bind(Client::class, function (Application $app) use ($config) {
            return new Client(
                clientId: Arr::get($config, 'client_id'),
                clientSecret: Arr::get($config, 'client_secret'),
                region: LegacyRegion::from(Arr::get($config, 'region', 'eu')),
                locale: Arr::get($config, 'locale'),
            );
        });

        /**
         * TODO: Remove when refactor is complete.
         */
        $this->app->singleton(BlizzardService::class, function (Application $app) use ($config) {
            return new BlizzardService(
                $app->make(Client::class),
                $config,
            );
        });

        /**
         * TODO: Remove when refactor is complete.
         */
        $this->app->singleton(MediaService::class, function (Application $app) use ($config) {
            return new MediaService(
                Arr::get($config, 'region'),
                $app->make(FilesystemManager::class),
                Arr::get($config, 'filesystem', 'public'),
            );
        });

        $this->app->singleton(BlizzardConnector::class, function (Application $app) use ($config) {
            return new BlizzardConnector(
                clientId: Arr::get($config, 'client_id'),
                clientSecret: Arr::get($config, 'client_secret'),
                gameVersion: GameVersion::fromName(Arr::get($config, 'game_version', 'Anniversary')),
                region: Region::from(Arr::get($config, 'region', 'eu')),
                locale: Arr::get($config, 'locale'),
            );
        });

        $this->app->singleton(MirrorPathResolver::class, function () use ($config) {
            return new MirrorPathResolver(
                region: Region::from(Arr::get($config, 'region', 'eu')),
            );
        });

        $this->app->singleton(RenderConnector::class, function (Application $app) use ($config) {
            return new RenderConnector(
                region: Region::from(Arr::get($config, 'region', 'eu')),
                disk: $app->make(FilesystemManager::class)->disk(Arr::get($config, 'filesystem', 'public')),
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->app->alias(BlizzardConnector::class, BlizzardFacade::class);
        $this->app->alias(MirrorPathResolver::class, BlizzardRenderPath::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            BlizzardConnector::class,
            MirrorPathResolver::class,
            RenderConnector::class,

            /**
             * TODO: Remove when refactor is complete.
             */
            Client::class,
            BlizzardService::class,
            MediaService::class,
        ];
    }
}
