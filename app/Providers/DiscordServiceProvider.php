<?php

namespace App\Providers;

use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\RateLimitCache;
use Illuminate\Support\Facades\Cache;
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
            DiscordConnector::class,
        ];
    }
}
