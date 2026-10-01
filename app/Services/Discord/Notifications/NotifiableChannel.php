<?php

namespace App\Services\Discord\Notifications;

use App\Services\Discord\Contracts\Resources\Channel;
use App\Services\Discord\Discord;
use App\Services\Discord\Stubs\ChannelStub;
use Illuminate\Notifications\Notifiable;
use RuntimeException;

class NotifiableChannel
{
    use Notifiable;

    public function __construct(
        protected Channel $channel,
    ) {}

    /**
     * Get the unique identifier for the notifiable.
     */
    public function getKey(): string
    {
        return $this->channel->id;
    }

    /**
     * Get the Discord channel resource associated with this notifiable channel.
     */
    public function channel(): Channel
    {
        return $this->channel;
    }

    /**
     * Create a NotifiableChannel instance from a Discord channel ID by resolving it to a Channel resource using the provided Discord service.
     *
     * @param  string  $channelId  The ID of the Discord channel to resolve
     * @param  Discord  $discord  The Discord service instance used to resolve the channel
     */
    public static function fromChannelId(string $channelId, Discord $discord): self
    {
        return new self($discord->getChannel($channelId));
    }

    /**
     * Create a NotifiableChannel instance from a configuration key, which looks up the corresponding channel ID in the config and resolves it to a Channel resource.
     *
     * @param  string  $key  The configuration key for the channel (e.g., 'announcements', 'officer')
     * @param  Discord  $discord  The Discord service instance used to resolve the channel
     */
    public static function fromConfig(string $key, Discord $discord): self
    {
        return new self($discord->getChannel(self::channelIdFromConfig($key)));
    }

    /**
     * Create a NotifiableChannel instance from a configuration key without calling the Discord API, using a stub that carries only the channel ID.
     *
     * @param  string  $key  The configuration key for the channel (e.g., 'announcements', 'officer')
     */
    public static function stubFromConfig(string $key): self
    {
        return new self(new ChannelStub(self::channelIdFromConfig($key)));
    }

    /**
     * Look up the Discord channel ID configured for the given key.
     *
     * @throws RuntimeException
     */
    private static function channelIdFromConfig(string $key): string
    {
        $channelId = config("services.discord.channels.{$key}");

        if (! $channelId) {
            throw new RuntimeException("No Discord channel configured for key: {$key}");
        }

        return $channelId;
    }
}
