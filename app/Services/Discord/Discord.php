<?php

namespace App\Services\Discord;

use App\Exceptions\MisconfigurationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Discord
{
    /**
     * Create a new instance of the Discord service.
     *
     * @param  DiscordClient  $client  The Discord client for making API requests.
     * @param  array  $config  Optional configuration for the Discord service.
     */
    public function __construct(
        protected readonly DiscordClient $client,
        protected array $config = []
    ) {}

    /**
     * Helper method to retrieve configuration values with a default fallback.
     *
     * @throws MisconfigurationException if a required configuration key is missing.
     */
    private function config(string $key, mixed $default = null): mixed
    {
        // If no default is provided and the key is missing, throw an exception
        if (! $default && ! Arr::has($this->config, $key)) {
            throw new MisconfigurationException("Missing required Discord configuration key: {$key}");
        }

        return Arr::get($this->config, $key, $default);
    }

    // ==================== Channels ====================

    /**
     * Retreives a channel
     *
     * @param  string  $channelId  The ID of the channel to retrieve.
     * @return Channel The retrieved channel.
     */
    public function getChannel(string $channelId): Channel
    {
        return Channel::from($this->client->get("channels/{$channelId}")->json());
    }

    /**
     * Lists channels in a guild
     *
     * @param  string|null  $guildId  The ID of the guild to list channels from. Defaults to the configured server_id.
     * @return Collection<Channel> A collection of channels in the guild.
     */
    public function listGuildChannels(?string $guildId = null): Collection
    {
        $guildId = $guildId ?? $this->config('server_id', null);

        return Channel::collect($this->client->get("guilds/{$guildId}/channels")->json(), Collection::class);
    }
}
