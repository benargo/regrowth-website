<?php

namespace App\Providers;

use App\Services\Discord\DiscordGuildService;
use App\Services\Discord\DiscordMessageService;
use App\Services\Discord\DiscordRoleService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\Provider as DiscordProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class DiscordServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DiscordGuildService::class, function (Application $app) {
            $config = config('services.discord');

            return new DiscordGuildService($config['token'], $config['guild_id']);
        });

        $this->app->singleton(DiscordMessageService::class, function (Application $app) {
            $config = config('services.discord');

            return new DiscordMessageService($config['token']);
        });

        $this->app->singleton(DiscordRoleService::class, function (Application $app) {
            $config = config('services.discord');

            return new DiscordRoleService($config['token'], $config['guild_id']);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register the Discord Socialite provider
        $this->app['events']->listen(SocialiteWasCalled::class, function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', DiscordProvider::class);
        });
    }
}
