<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Discord\DiscordChannel;
use NotificationChannels\Discord\DiscordMessage;

class LevelCapAchieved extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $characterNames
     */
    public function __construct(
        public array $characterNames,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [DiscordChannel::class];
    }

    /**
     * Get the Discord representation of the notification.
     */
    public function toDiscord(object $notifiable): DiscordMessage
    {
        $count = count($this->characterNames);
        $isSingular = $count === 1;

        $title = $isSingular
            ? 'Level 70 Achieved!'
            : 'Level 70 Achieved!';

        $description = $isSingular
            ? sprintf('Congratulations to **%s** on reaching level 70!', $this->characterNames[0])
            : sprintf('Congratulations to %s on reaching level 70!', $this->formatNames());

        return DiscordMessage::create()->embed([
            'title' => $title,
            'color' => 5763719, // Green color
            'description' => $description,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Format multiple names with proper grammar (e.g., "Alice, Bob, and Charlie").
     */
    protected function formatNames(): string
    {
        $names = array_map(fn ($name) => "**{$name}**", $this->characterNames);

        if (count($names) === 2) {
            return implode(' and ', $names);
        }

        $lastName = array_pop($names);

        return implode(', ', $names).', and '.$lastName;
    }
}
