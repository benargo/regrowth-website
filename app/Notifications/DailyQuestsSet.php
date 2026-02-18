<?php

namespace App\Notifications;

use App\Models\DiscordRole;
use App\Models\TBC\DailyQuestNotification;

class DailyQuestsSet
{
    public function __construct(
        public DailyQuestNotification $notification
    ) {}

    /**
     * Get the Discord message payload for this notification.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return [
            'mention_roles' => DiscordRole::where('name', 'Daily Quest Subscriber')->get()->pluck('id')->toArray(),
            'embeds' => [$this->buildEmbed()],
        ];
    }

    protected function buildEmbed(): array
    {
        $fields = [];

        $typeMap = [
            'cookingQuest' => 'Cooking',
            'fishingQuest' => 'Fishing',
            'dungeonQuest' => 'Normal dungeon',
            'heroicQuest' => 'Heroic dungeon',
            'pvpQuest' => 'PvP battleground',
        ];

        foreach ($typeMap as $relation => $label) {
            $quest = $this->notification->$relation;
            if ($quest) {
                $value = $quest->name;

                if (in_array($relation, ['dungeonQuest', 'heroicQuest']) && $quest->instance) {
                    $value .= ' ('.$quest->instance->value.')';
                }

                $fields[] = [
                    'name' => $label,
                    'value' => $value,
                    'inline' => false,
                ];
            }
        }

        $author = $this->notification->sentBy;

        return [
            'title' => '📜 Today\'s Daily Quests',
            'color' => 15844367, // Gold color
            'description' => 'Here are the daily quests for '.$this->notification->date->format('l, d F Y').'.',
            'url' => route('daily-quests.index'),
            'fields' => $fields,
            'timestamp' => now()->toIso8601String(),
            'footer' => [
                'text' => 'Posted by '.$author?->nickname.'.',
                'icon_url' => $author?->avatarUrl,
            ],
        ];
    }
}
