<?php

namespace Database\Factories\TBC;

use App\Models\TBC\DailyQuest;
use App\Models\TBC\DailyQuestNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TBC\DailyQuestNotification>
 */
class DailyQuestNotificationFactory extends Factory
{
    protected $model = DailyQuestNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now('Europe/Paris')->startOfDay()->addHours(3),
            'discord_message_id' => (string) fake()->numerify('#########'),
            'cooking_quest_id' => DailyQuest::factory()->cooking(),
            'fishing_quest_id' => DailyQuest::factory()->fishing(),
            'dungeon_quest_id' => DailyQuest::factory()->dungeon(),
            'heroic_quest_id' => DailyQuest::factory()->heroic(),
            'pvp_quest_id' => DailyQuest::factory()->pvp(),
            'sent_by_user_id' => User::factory(),
            'updated_by_user_id' => null,
        ];
    }

    public function withoutDiscordMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'discord_message_id' => null,
        ]);
    }

    public function forDate(\Carbon\Carbon $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }

    public function updated(): static
    {
        return $this->state(fn (array $attributes) => [
            'updated_by_user_id' => User::factory(),
        ]);
    }
}
