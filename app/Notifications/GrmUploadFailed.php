<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Discord\DiscordChannel;
use NotificationChannels\Discord\DiscordMessage;

class GrmUploadFailed extends Notification implements ShouldQueue
{
    use Queueable;

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
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [DiscordChannel::class];
    }

    public function toDiscord(object $notifiable): DiscordMessage
    {
        $embed = [
            'title' => $this->exceptionMessage
                ? 'GRM Upload Processing Failed'
                : 'GRM Upload Processing Completed with Errors',
            'color' => 15158332,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($this->exceptionMessage) {
            $embed['description'] = "The GRM upload job failed completely after all retry attempts.\n\n**Error:** {$this->exceptionMessage}";
        } else {
            $embed['fields'] = [
                ['name' => 'Processed', 'value' => (string) $this->processedCount, 'inline' => true],
                ['name' => 'Errors', 'value' => (string) $this->errorCount, 'inline' => true],
            ];
            $embed['image'] = ['url' => config('app.url').'/images/jaina_broken.webp'];

            $errorList = array_slice($this->errors, 0, 10);
            $errorText = implode("\n", array_map(fn ($e) => "- {$e}", $errorList));
            if (count($this->errors) > 10) {
                $errorText .= "\n... and ".(count($this->errors) - 10).' more errors';
            }
            $embed['description'] = "**Errors:**\n{$errorText}";
        }

        return DiscordMessage::create()->embed($embed);
    }
}
