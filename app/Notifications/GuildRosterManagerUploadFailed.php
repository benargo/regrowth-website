<?php

namespace App\Notifications;

use App\Http\Integrations\Discord\Data\Messages\EmbedData;
use App\Http\Integrations\Discord\Data\Messages\EmbedFieldData;
use App\Http\Integrations\Discord\Data\Messages\EmbedMediaData;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Notifications\Discord\DiscordNotification;

class GuildRosterManagerUploadFailed extends DiscordNotification
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public int $processedCount,
        public int $errorCount,
        public array $errors = [],
        public ?string $exceptionMessage = null,
    ) {}

    /**
     * Get the payload to send to Discord for this notification.
     */
    public function toMessage(): MessagePayloadData
    {
        if ($this->exceptionMessage) {
            return MessagePayloadData::from([
                'embeds' => [EmbedData::from([
                    'title' => 'GRM Upload Processing Failed',
                    'description' => "The GRM upload job failed completely after all retry attempts.\n\n**Error:** {$this->exceptionMessage}",
                    'color' => 15158332,
                    'timestamp' => now()->toIso8601String(),
                ])],
            ]);
        }

        $errorList = array_slice($this->errors, 0, 10);
        $errorText = implode("\n", array_map(fn ($e) => "- {$e}", $errorList));
        if (count($this->errors) > 10) {
            $errorText .= "\n... and ".(count($this->errors) - 10).' more errors';
        }

        return MessagePayloadData::from([
            'embeds' => [EmbedData::from([
                'title' => 'GRM Upload Processing Completed with Errors',
                'description' => "**Errors:**\n{$errorText}",
                'color' => 15158332,
                'image' => EmbedMediaData::from(['url' => config('app.url').'/images/jaina_broken.webp']),
                'fields' => [
                    new EmbedFieldData('Processed', (string) $this->processedCount, true),
                    new EmbedFieldData('Errors', (string) $this->errorCount, true),
                ],
                'timestamp' => now()->toIso8601String(),
            ])],
        ]);
    }
}
