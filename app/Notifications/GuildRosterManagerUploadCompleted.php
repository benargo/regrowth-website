<?php

namespace App\Notifications;

use App\Http\Integrations\Discord\Data\Messages\EmbedData;
use App\Http\Integrations\Discord\Data\Messages\EmbedFieldData;
use App\Http\Integrations\Discord\Data\Messages\EmbedMediaData;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Notifications\Discord\DiscordMessage;

class GuildRosterManagerUploadCompleted extends DiscordMessage
{
    public function __construct(
        public string $gameVersionSlug,
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
                'url' => route('addons.loot-tool.export', ['game_version' => $this->gameVersionSlug]),
                'color' => 5763719,
                'image' => EmbedMediaData::from(['url' => config('app.url').'/images/jaina_thumbsup.webp']),
                'fields' => $fields,
                'timestamp' => now()->toIso8601String(),
            ])],
        ]);
    }
}
