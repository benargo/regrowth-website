<?php

namespace App\Contracts\Notifications;

use App\Models\DiscordNotification;
use App\Models\User;
use App\Services\Discord\Payloads\MessagePayload;
use Illuminate\Contracts\Auth\Authenticatable;

interface DiscordMessage
{
    /**
     * Get the notification channels.
     *
     * @return string The notification channels to send the message through (e.g. a custom DiscordChannel class)
     */
    public function via(object $notifiable): string;

    /**
     * Get the payload to send to Discord for this notification.
     */
    public function toMessage(): MessagePayload;

    /**
     * Get the array of data to store in the database for this notification.
     */
    public function toDatabase(object $notifiable): array;

    /**
     * Get the notification instance this notification will update, if any.
     */
    public function updates(): ?DiscordNotification;

    /**
     * Get the user who sent this notification, if any.
     */
    public function sender(): ?Authenticatable;
}
