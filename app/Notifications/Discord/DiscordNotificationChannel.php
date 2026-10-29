<?php

namespace App\Notifications\Discord;

use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\MessageNotFoundException;
use App\Http\Integrations\Discord\Requests\Messages\CreateMessageRequest;
use App\Http\Integrations\Discord\Requests\Messages\EditMessageRequest;
use App\Models\DiscordChannel;
use App\Models\DiscordMessage;
use App\Notifications\Contracts\ShouldUpdateExisting;

/**
 * Laravel notification channel that posts or edits a DiscordNotification in a Discord channel and records it as a DiscordMessage.
 */
class DiscordNotificationChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct(
        private readonly DiscordConnector $discord,
    ) {}

    /**
     * Send the given notification, routing to update or create as appropriate.
     *
     * @param  DiscordChannel  $notifiable  The Discord channel to post to
     * @param  DiscordNotification  $notification  The notification instance to send
     */
    public function send(DiscordChannel $notifiable, DiscordNotification $notification): void
    {
        $existing = $notification instanceof ShouldUpdateExisting
            ? $notification->existingMessage($notifiable)
            : null;

        if ($existing?->message_id) {
            $this->updateMessage($notifiable, $notification, $existing);

            return;
        }

        $this->createMessage($notifiable, $notification);
    }

    /**
     * Edit an existing Discord message and update its database record. When the Discord message
     * no longer exists, delete the record.
     */
    private function updateMessage(DiscordChannel $notifiable, DiscordNotification $notification, DiscordMessage $existing): void
    {
        $channelId = $notifiable->id;
        $messageId = $existing->message_id;

        try {
            $payload = $notification->toMessage();

            $this->discord->send(new EditMessageRequest($channelId, $messageId, $payload));

            $existing->withoutEvents(function () use ($existing, $notification, $payload): void {
                $existing->update([
                    'payload' => $payload->toArray(),
                    'created_by_user_id' => $notification->sender()?->id,
                ]);
            });

            // Sync related models only when relatedModels is non-null; null leaves existing pivot rows unchanged.
            if ($notification->relatedModels !== null) {
                $existing->syncRelatedModels($notification->mapRelatedModels());
            }
        } catch (MessageNotFoundException) {
            $existing->withoutEvents(function () use ($existing): void {
                $existing->delete();
            });
        }
    }

    /**
     * Post a new Discord message and persist the database record with related models.
     */
    private function createMessage(DiscordChannel $notifiable, DiscordNotification $notification): void
    {
        $payload = $notification->toMessage();

        $message = $this->discord
            ->send(new CreateMessageRequest($notifiable->id, $payload))
            ->dto();

        $data = array_merge($notification->toDatabase($notifiable, $payload), [
            'message_id' => $message->id,
        ]);

        $record = $notifiable->messages()->create($data);

        $record->syncRelatedModels($notification->mapRelatedModels());
    }
}
