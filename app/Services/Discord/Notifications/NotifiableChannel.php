<?php

namespace App\Services\Discord\Notifications;

use App\Services\Discord\Contracts\Resources\Channel;
use App\Services\Discord\Discord;
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
        $channelId = config("services.discord.channels.{$key}");

        if (! $channelId) {
            throw new RuntimeException("No Discord channel configured for key: {$key}");
        }

        return new self($discord->getChannel($channelId));
    }
}
