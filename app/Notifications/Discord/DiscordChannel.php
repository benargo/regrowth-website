<?php

namespace App\Notifications\Discord;

use App\Http\Integrations\Discord\Contracts\Channel;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\MessageNotFoundException;
use App\Http\Integrations\Discord\Requests\Messages\CreateMessageRequest;
use App\Http\Integrations\Discord\Requests\Messages\EditMessageRequest;
use App\Http\Integrations\Discord\Requests\Messages\GetMessageRequest;
use App\Models\DiscordNotification;
use Illuminate\Support\Facades\DB;

/**
 * Laravel notification channel that posts or edits a DiscordMessage in a Discord channel and records it as a DiscordNotification.
 */
class DiscordChannel
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
     * @param  Channel  $notifiable  The Discord channel to post to
     * @param  DiscordMessage  $notification  The notification instance to send
     */
    public function send(Channel $notifiable, DiscordMessage $notification): void
    {
        if (property_exists($notification, 'updates') && $notification->updates?->message_id) {
            $this->updateMessage($notifiable, $notification);

            return;
        }

        $this->createMessage($notifiable, $notification);
    }

    /**
     * Edit an existing Discord message and update its database record.
     * Falls through to createMessage() if the Discord message no longer exists.
     */
    private function updateMessage(Channel $notifiable, DiscordMessage $notification): void
    {
        $channelId = $notifiable->id;
        $messageId = $notification->updates->message_id;

        try {
            $this->discord->send(new GetMessageRequest($channelId, $messageId));
            $payload = $notification->toMessage();

            $this->discord->send(new EditMessageRequest($channelId, $messageId, $payload));

            // Update the record without firing model events.
            $notification->updates->withoutEvents(function () use ($notification, $payload) {
                $notification->updates->update([
                    'payload' => $payload->toArray(),
                    'created_by_user_id' => $notification->sender()?->id,
                ]);
            });

            // Sync related models only when relatedModels is non-null; null leaves existing pivot rows unchanged.
            if ($notification->relatedModels !== null) {
                $this->syncRelatedModels($notification->updates, $notification->mapRelatedModels());
            }
        } catch (MessageNotFoundException $e) {
            // The Discord message no longer exists: delete the record without model events and create a new message.
            $notification->updates->withoutEvents(function () use ($notification) {
                $notification->updates->delete();
            });

            $this->createMessage($notifiable, $notification);
        }
    }

    /**
     * Post a new Discord message and persist the database record with related models.
     */
    private function createMessage(Channel $notifiable, DiscordMessage $notification): void
    {
        $message = $this->discord
            ->send(new CreateMessageRequest($notifiable->id, $notification->toMessage()))
            ->dto();

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

            // Upsert the desired pivot rows, rewriting the unique-by column to itself.
            $record->relatedModels()->upsert(
                $desired,
                ['discord_notification_id', 'model_type', 'model_id'],
                ['discord_notification_id'],
            );

            // Delete existing pivot rows that are not in the desired model pairs.
            $keepIds = $record->relatedModels()
                ->where(function ($query) use ($desired) {
                    foreach ($desired as $row) {
                        $query->orWhere(function ($subQuery) use ($row) {
                            $subQuery
                                ->where('model_type', $row['model_type'])
                                ->where('model_id', $row['model_id']);
                        });
                    }
                })
                ->pluck('id');

            $record->relatedModels()
                ->whereNotIn('id', $keepIds)
                ->delete();
        });
    }
}
