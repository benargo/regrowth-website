<?php

namespace App\Notifications;

use App\Models\Character;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use NotificationChannels\Discord\DiscordChannel;
use NotificationChannels\Discord\DiscordMessage;

class LevelCapAchieved extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var array<int, string>
     */
    public array $characterNames;

    /**
     * @param  Collection<int, Character>|array<int, Character>  $characters
     */
    public function __construct(
        public Collection|array $characters,
    ) {
        $this->characters = $characters instanceof Collection ? $characters : collect($characters);
        $this->characterNames = $this->characters->pluck('name')->toArray();
    }

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

        $embed = [
            'title' => $title,
            'color' => 5763719, // Green color
            'description' => $description,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($isSingular) {
            $imageUrl = $this->getPlacementImageUrl($this->characters->first());
            if ($imageUrl !== null) {
                $embed['image'] = ['url' => $imageUrl];
            }
        }

        return DiscordMessage::create()->embed($embed);
    }

    /**
     * Get the placement image URL for a character if they are in the top 3.
     */
    protected function getPlacementImageUrl(Character $character): ?string
    {
        $placement = $this->getPlacement($character);

        return match ($placement) {
            1 => 'https://regrowth.gg/images/raceto70_firstplace.webp',
            2 => 'https://regrowth.gg/images/raceto70_secondplace.webp',
            3 => 'https://regrowth.gg/images/raceto70_thirdplace.webp',
            default => null,
        };
    }

    /**
     * Get the placement of a character in the race to level 70.
     */
    protected function getPlacement(Character $character): int
    {
        $earlierCount = Character::query()
            ->whereNotNull('reached_level_cap_at')
            ->where('reached_level_cap_at', '<', $character->reached_level_cap_at)
            ->count();

        return $earlierCount + 1;
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
