<?php

namespace App\Notifications;

use App\Http\Integrations\Discord\Contracts\Channel;
use App\Http\Integrations\Discord\Data\Messages\EmbedData;
use App\Http\Integrations\Discord\Data\Messages\EmbedFieldData;
use App\Http\Integrations\Discord\Data\Messages\EmbedMediaData;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Notifications\Discord\DiscordMessage;

class GrmUploadCompleted extends DiscordMessage
{
    public function __construct(
        public int $processedCount,
        public int $skippedCount = 0,
        public int $warningCount = 0,
    ) {}

    /**
     * Get the payload to send to Discord for this notification.
     */
    public function toMessage(): MessagePayloadData
    {
        $fields = [new EmbedFieldData('Processed', (string) $this->processedCount, true)];

        if ($this->skippedCount > 0) {
            $fields[] = new EmbedFieldData('Skipped (too low level)', (string) $this->skippedCount, true);
        }

        if ($this->warningCount > 0) {
            $fields[] = new EmbedFieldData('Skipped (API issues)', (string) $this->warningCount, true);
        }

        return MessagePayloadData::from([
            'embeds' => [EmbedData::from([
                'title' => 'GRM Upload Processing Completed',
                'description' => 'Officers should make sure they update the RegrowthLootTool with new data.',
                'url' => route('management.addon.export'),
                'color' => 5763719,
                'image' => EmbedMediaData::from(['url' => config('app.url').'/images/jaina_thumbsup.webp']),
                'fields' => $fields,
                'timestamp' => now()->toIso8601String(),
            ])],
        ]);
    }

    /**
     * Get the array of data to store in the database for this notification.
     */
    public function toDatabase(Channel $notifiable): array
    {
        return [
            'type' => self::class,
            'channel_id' => $notifiable->id,
            'payload' => $this->toMessage()->toArray(),
        ];
    }
}
