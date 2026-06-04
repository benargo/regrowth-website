<?php

namespace App\Providers;

use App\Contracts\HasBlizzardIcons;
use App\Facades\Blizzard as BlizzardFacade;
use App\Facades\BlizzardRenderPath;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\GameVersion;
use App\Http\Integrations\Blizzard\Middleware\EagerlyMirrorAssets;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Support\MirrorPaths;
use App\Support\MediaLibrary\BlizzardIconPathGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

class BlizzardServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $config = config('services.blizzard');

        $this->app->singleton(BlizzardConnector::class, function (Application $app) use ($config) {
            return new BlizzardConnector(
                clientId: Arr::get($config, 'client_id'),
                clientSecret: Arr::get($config, 'client_secret'),
                gameVersion: GameVersion::fromName(Arr::get($config, 'game_version', 'Anniversary')),
                region: Region::from(Arr::get($config, 'region', 'eu')),
                locale: Arr::get($config, 'locale'),
                defaultRealmSlug: Arr::get($config, 'realm.slug'),
                defaultGuildSlug: Arr::get($config, 'guild.slug'),
                eagerlyMirrorAssets: $app->make(EagerlyMirrorAssets::class),
            );
        });

        $this->app->singleton(MirrorPaths::class, function () use ($config) {
            return new MirrorPaths(
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
        $this->app->alias(MirrorPaths::class, BlizzardRenderPath::class);

        // Register the Blizzard icon path generator against the marker interface.
        // Spatie's PathGeneratorFactory matches via is_a(), so this single
        // registration covers every model implementing HasBlizzardIcons.
        PathGeneratorFactory::setCustomPathGenerators(
            HasBlizzardIcons::class,
            BlizzardIconPathGenerator::class,
        );

        // Define a rate limiter for the FetchGuildRoster job to prevent it from being dispatched too frequently.
        RateLimiter::for('fetch-guild-roster-job', function (object $job) {
            return Limit::perHour(1);
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
            BlizzardConnector::class,
            MirrorPaths::class,
            RenderConnector::class,
        ];
    }
}
