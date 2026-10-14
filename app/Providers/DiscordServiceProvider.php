<?php

namespace App\Providers;

use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\RateLimitCache;
use App\Jobs\Discord\SyncRoles;
use App\Jobs\Discord\SyncUser;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\DiscordClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use SocialiteProviders\Discord\Provider as DiscordProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class DiscordServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        /**
         * Saloon connector for the Discord API. Throws when the bot token or server id is not configured.
         */
        $this->app->singleton(DiscordConnector::class, function (): DiscordConnector {
            $token = (string) config('services.discord.token');

            if ($token === '') {
                throw new RuntimeException('Discord is not configured: services.discord.token is empty.');
            }

            if ((string) config('services.discord.server_id') === '') {
                throw new RuntimeException('Discord is not configured: services.discord.server_id is empty.');
            }

            $appUrl = config('app.url');

            return new DiscordConnector(
                $token,
                "DiscordBot ({$appUrl}, 1.0)",
                new RateLimitCache(Cache::store()->tags(['discord', 'discord-rate-limit'])),
            );
        });

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
            if ($event->job->resolveName() === SyncRoles::class) {
                $jobs = User::all()->map(fn (User $user) => new SyncUser($user->id));
                Bus::batch($jobs)->dispatch();
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
            DiscordConnector::class,
            DiscordClient::class,
            Discord::class,
        ];
    }
}
