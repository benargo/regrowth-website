<?php

namespace App\Services\Discord\Notifications;

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
     * @param  Notification  $notification  The notification instance to send
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (property_exists($notification, 'updates') && $notification->updates?->message_id) {
            try {
                $existingMessage = $this->discord->getChannelMessage($notifiable->channel(), $notification->updates->message_id);
                $payload = $notification->toMessage();

                $this->discord->editMessage($existingMessage, $payload);

                // Update without model events to avoid re-triggering observers on a routine payload refresh.
                $notification->updates->withoutEvents(function () use ($notification, $payload) {
                    $notification->updates->update([
                        'payload' => $payload->toArray(),
                        'created_by_user_id' => $notification->sender()?->id,
                    ]);
                });

                return;
            } catch (RuntimeException $e) {
                // Stale message_id (e.g. manually deleted in Discord) — drop the record and fall through to create.
                $notification->updates->withoutEvents(function () use ($notification) {
                    $notification->updates->delete();
                });
            }
        }

        $message = $this->discord->createMessage($notifiable->channel(), $notification->toMessage());

        $data = array_merge($notification->toDatabase($notifiable), [
            'message_id' => $message->id,
        ]);

        $record = DiscordNotification::create($data);

        $this->syncRelatedModels($record, $notification->mapRelatedModels());
    }

    /**
     * Sync the pivot rows for the given notification's related models.
     *
     * @param  array<string, list<int|string>>  $relatedModels
     */
    protected function syncRelatedModels(DiscordNotification $record, array $relatedModels): void
    {
        $record->relatedModels()->delete();

        $rows = [];
        foreach ($relatedModels as $modelClass => $ids) {
            foreach ($ids as $id) {
                $rows[] = ['model_type' => $modelClass, 'model_id' => $id];
            }
        }

        if ($rows !== []) {
            $record->relatedModels()->createMany($rows);
        }
    }
}
