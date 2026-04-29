<?php

namespace App\Services\Discord\Notifications;

use App\Contracts\Notifications\DiscordMessage;
use App\Models\DiscordNotification;
use App\Services\Discord\Discord;
use RuntimeException;

class Driver
{
    /**
     * Create a new driver instance.
     */
    public function __construct(
        private readonly Discord $discord
    ) {}

    /**
     * Send the given notification.
     *
     * @param  object  $notifiable  The notifiable entity (e.g., a user or a channel wrapper)
     * @param  DiscordMessage  $notification  The notification instance to send
     */
    public function send(object $notifiable, DiscordMessage $notification): void
    {
        $updates = $notification->updates();

        // If the notification already has a Discord message ID, attempt to fetch and edit that message instead of creating a new one
        if ($updates?->message_id) {
            try {
                $existingMessage = $this->discord->getChannelMessage($notifiable->channel(), $updates->message_id);
                $payload = $notification->toMessage();

                $this->discord->editMessage($existingMessage, $payload);

                // Update the existing notification's payload and sender information in the database without firing model events
                $updates->withoutEvents(function () use ($notification, $payload, $updates) {
                    $updates->update([
                        'payload' => $payload->toArray(),
                        'created_by_user_id' => $notification->sender()?->id,
                    ]);
                });

                return; // Exit after successfully editing the existing message
            } catch (RuntimeException $e) {
                // If fetching the existing message fails (e.g., it was deleted), we should delete the database entry to remove
                // the stale message ID and then send a new message.
                $updates->withoutEvents(function () use ($updates) {
                    $updates->delete();
                });
            }
        }

        // If we couldn't edit an existing message, or there was no message ID, create a new message
        $message = $this->discord->createMessage($notifiable->channel(), $notification->toMessage());

        // Create a new database entry for this notification
        $data = array_merge($notification->toDatabase($notifiable), [
            'message_id' => $message->id,
        ]);

        DiscordNotification::create($data);
    }
}
