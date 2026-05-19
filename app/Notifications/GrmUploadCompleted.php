<?php

namespace App\Notifications;

use App\Services\Discord\Notifications\Notification;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Embed;
use App\Services\Discord\Resources\EmbedField;
use App\Services\Discord\Resources\EmbedMedia;

class GrmUploadCompleted extends Notification
{
    public function __construct(
        public int $processedCount,
        public int $skippedCount = 0,
        public int $warningCount = 0,
    ) {}

    /**
     * Get the payload to send to Discord for this notification.
     */
    public function toMessage(): MessagePayload
    {
        $fields = [new EmbedField('Processed', (string) $this->processedCount, true)];

        if ($this->skippedCount > 0) {
            $fields[] = new EmbedField('Skipped (too low level)', (string) $this->skippedCount, true);
        }

        if ($this->warningCount > 0) {
            $fields[] = new EmbedField('Skipped (API issues)', (string) $this->warningCount, true);
        }

        return MessagePayload::from([
            'embeds' => [new Embed(
                title: 'GRM Upload Processing Completed',
                description: 'Officers should make sure they update the RegrowthLootTool with new data.',
                url: route('dashboard.addon.export'),
                color: 5763719,
                image: new EmbedMedia(config('app.url').'/images/jaina_thumbsup.webp'),
                fields: $fields,
                timestamp: now()->toIso8601String(),
            )],
        ]);
    }

    /**
     * Get the array of data to store in the database for this notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => self::class,
            'channel_id' => $notifiable->channel()->id,
            'payload' => $this->toMessage()->toArray(),
        ];
    }
}
