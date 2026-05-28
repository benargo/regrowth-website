<?php

namespace App\Services\Discord\Notifications;

use App\Models\DiscordNotification;
use App\Services\Discord\Discord;
use App\Services\Discord\Exceptions\MessageNotFoundException;
use Illuminate\Support\Facades\DB;

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
            } catch (MessageNotFoundException $e) {
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
     * @param  list<array{model_id: int|string, model_type: string}>  $relatedModels
     */
    protected function syncRelatedModels(DiscordNotification $record, array $relatedModels): void
    {
        $desired = array_map(fn ($entry) => [
            'discord_notification_id' => $record->id,
            'model_type' => $entry['model_type'],
            'model_id' => (string) $entry['model_id'],
        ], $relatedModels);

        DB::transaction(function () use ($record, $desired) {
            if ($desired === []) {
                $record->relatedModels()->delete();

                return;
            }

            $record->relatedModels()->upsert(
                $desired,
                ['discord_notification_id', 'model_type', 'model_id'],
                [],
            );

            // Delete any existing pivot rows whose (model_type, model_id) tuple is not in $desired.
            // The unique index on (discord_notification_id, model_type, model_id) makes this efficient.
            $placeholders = implode(',', array_fill(0, count($desired), '(?, ?)'));
            $bindings = [];
            foreach ($desired as $row) {
                $bindings[] = $row['model_type'];
                $bindings[] = $row['model_id'];
            }

            $record->relatedModels()
                ->whereRaw("(model_type, model_id) NOT IN ({$placeholders})", $bindings)
                ->delete();
        });
    }
}
