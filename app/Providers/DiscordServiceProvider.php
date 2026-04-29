<?php

namespace App\Providers;

use App\Services\Discord\Discord;
use App\Services\Discord\DiscordClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
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
        /** API client */
        $this->app->singleton(DiscordClient::class, function (Application $app) {
            return new DiscordClient(config('services.discord.token'));
        });

        /** Main Discord service */
        $this->app->singleton(Discord::class, function (Application $app) {
            $config = Arr::only(config('services.discord'), ['server_id', 'channels']);

            return new Discord($app->make(DiscordClient::class), $config);
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

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            DiscordClient::class,
            Discord::class,
        ];
    }
}
