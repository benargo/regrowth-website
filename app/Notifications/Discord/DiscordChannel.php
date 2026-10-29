<?php

namespace App\Notifications\Discord;

use App\Http\Integrations\Discord\Contracts\Channel;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\MessageNotFoundException;
use App\Http\Integrations\Discord\Requests\Messages\CreateMessageRequest;
use App\Http\Integrations\Discord\Requests\Messages\EditMessageRequest;
use App\Models\DiscordNotification;
use App\Notifications\Contracts\ShouldUpdateExisting;
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
        $existing = $notification instanceof ShouldUpdateExisting
            ? $notification->existingNotification($notifiable)
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
    private function updateMessage(Channel $notifiable, DiscordMessage $notification, DiscordNotification $existing): void
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
                $this->syncRelatedModels($existing, $notification->mapRelatedModels());
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
    private function createMessage(Channel $notifiable, DiscordMessage $notification): void
    {
        $payload = $notification->toMessage();

        $message = $this->discord
            ->send(new CreateMessageRequest($notifiable->id, $payload))
            ->dto();

        $data = array_merge($notification->toDatabase($notifiable, $payload), [
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
        $desired = array_map(fn (array $entry): array => [
            'discord_notification_id' => $record->id,
            'model_type' => $entry['model_type'],
            'model_id' => (string) $entry['model_id'],
        ], $relatedModels);

        DB::transaction(function () use ($record, $desired): void {
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
