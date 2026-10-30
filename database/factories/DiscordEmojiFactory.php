<?php

namespace Database\Factories;

use App\Models\DiscordEmoji;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordEmoji>
 */
class DiscordEmojiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) fake()->unique()->numerify('##################'),
            'name' => fake()->unique()->lexify('emoji_????'),
            'animated' => false,
            'available' => true,
        ];
    }

    /**
     * Indicate the emoji is animated.
     */
    public function animated(): static
    {
        return $this->state(fn () => [
            'animated' => true,
        ]);
    }

    /**
     * Indicate the emoji is unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(fn () => [
            'available' => false,
        ]);
    }
}
