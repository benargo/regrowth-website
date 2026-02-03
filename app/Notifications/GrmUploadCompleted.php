<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Discord\DiscordChannel;
use NotificationChannels\Discord\DiscordMessage;

class GrmUploadCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $processedCount,
        public int $skippedCount = 0,
        public int $warningCount = 0,
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
        $fields = [
            [
                'name' => 'Processed',
                'value' => (string) $this->processedCount,
                'inline' => true,
            ],
        ];

        if ($this->skippedCount > 0) {
            $fields[] = [
                'name' => 'Skipped (too low level)',
                'value' => (string) $this->skippedCount,
                'inline' => true,
            ];
        }

        if ($this->warningCount > 0) {
            $fields[] = [
                'name' => 'Skipped (API issues)',
                'value' => (string) $this->warningCount,
                'inline' => true,
            ];
        }

        return DiscordMessage::create()->embed([
            'title' => 'GRM Upload Processing Completed',
            'url' => route('dashboard.addon.export'),
            'color' => 5763719,
            'fields' => $fields,
            'description' => 'Officers should make sure they update the RegrowthLootTool with new data.',
            'image' => ['url' => config('app.url').'/images/jaina_thumbsup.webp'],
            // 'image' => ['url' => 'https://regrowth.gg/images/jaina_thumbsup.webp'], // used for local testing
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
