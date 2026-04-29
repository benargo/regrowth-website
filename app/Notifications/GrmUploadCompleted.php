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
use Illuminate\Support\Collection;

class GrmUploadCompleted extends Notification implements DiscordMessage, ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $processedCount,
        public int $skippedCount = 0,
        public int $warningCount = 0,
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

    public function relationships(): Collection
    {
        return collect();
    }

    private function buildPayload(): MessagePayload
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
}
