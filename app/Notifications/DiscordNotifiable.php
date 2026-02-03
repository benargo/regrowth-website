<?php

namespace App\Notifications;

use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

class DiscordNotifiable
{
    use Notifiable;

    public function __construct(
        protected string $channel
    ) {}

    /**
     * Get the unique identifier for the notifiable.
     */
    public function getKey(): string
    {
        return $this->channel;
    }

    public static function officer(): self
    {
        return new self('officer');
    }

    public static function announcements(): self
    {
        return new self('announcements');
    }

    public static function channel(string $channelNameOrId): self
    {
        return new self($channelNameOrId);
    }

    public function routeNotificationForDiscord(Notification $notification): ?string
    {
        // If it's a numeric ID, return it directly
        if (is_numeric($this->channel)) {
            return $this->channel;
        }

        // Otherwise resolve from config
        return config("services.discord.channels.{$this->channel}");
    }
}
