<?php

namespace App\Notifications;

use App\Contracts\Notifications\DiscordMessage;
use App\Models\DiscordNotification;
use App\Services\Discord\Notifications\Driver as DiscordDriver;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Embed;
use App\Services\Discord\Resources\EmbedField;
use App\Services\Discord\Resources\EmbedMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GrmUploadFailed extends Notification implements DiscordMessage, ShouldQueue
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

    public function via(object $notifiable): string
    {
        return DiscordDriver::class;
    }

    public function toMessage(): MessagePayload
    {
        return $this->buildPayload();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => self::class,
            'channel_id' => $notifiable->channel()->id,
            'payload' => $this->buildPayload()->toArray(),
        ];
    }

    public function updates(): ?DiscordNotification
    {
        return null;
    }

    public function sender(): ?Authenticatable
    {
        return null;
    }

    private function buildPayload(): MessagePayload
    {
        if ($this->exceptionMessage) {
            return MessagePayload::from([
                'embeds' => [new Embed(
                    title: 'GRM Upload Processing Failed',
                    description: "The GRM upload job failed completely after all retry attempts.\n\n**Error:** {$this->exceptionMessage}",
                    color: 15158332,
                    timestamp: now()->toIso8601String(),
                )],
            ]);
        }

        $errorList = array_slice($this->errors, 0, 10);
        $errorText = implode("\n", array_map(fn ($e) => "- {$e}", $errorList));
        if (count($this->errors) > 10) {
            $errorText .= "\n... and ".(count($this->errors) - 10).' more errors';
        }

        return MessagePayload::from([
            'embeds' => [new Embed(
                title: 'GRM Upload Processing Completed with Errors',
                description: "**Errors:**\n{$errorText}",
                color: 15158332,
                image: new EmbedMedia(config('app.url').'/images/jaina_broken.webp'),
                fields: [
                    new EmbedField('Processed', (string) $this->processedCount, true),
                    new EmbedField('Errors', (string) $this->errorCount, true),
                ],
                timestamp: now()->toIso8601String(),
            )],
        ]);
    }
}
