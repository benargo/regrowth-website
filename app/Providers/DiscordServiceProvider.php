<?php

namespace App\Providers;

use App\Jobs\SyncDiscordRoles;
use App\Jobs\SyncDiscordUsers;
use App\Services\Discord\Discord;
use App\Services\Discord\DiscordClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
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
            return new DiscordClient(
                config('services.discord.token'),
                'DiscordBot ('.config('app.url').', 1.0)',
            );
        });

        /** Main Discord service */
        $this->app->singleton(Discord::class, function (Application $app) {
            return new Discord(
                $app->make(DiscordClient::class),
                (string) config('services.discord.server_id'),
                (array) config('services.discord.channels', []),
            );
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

        Queue::after(function (JobProcessed $event) {
            if ($event->job->resolveName() === SyncDiscordRoles::class) {
                SyncDiscordUsers::dispatch();
            }
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
