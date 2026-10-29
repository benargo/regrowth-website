<?php

namespace Database\Factories;

use App\Http\Integrations\Discord\Enums\ChannelType;
use App\Models\DiscordChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordChannel>
 */
class DiscordChannelFactory extends Factory
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
            'name' => fake()->unique()->slug(2),
            'type' => ChannelType::GuildText,
            'position' => fake()->numberBetween(0, 50),
        ];
    }

    /**
     * Indicate the channel is a category.
     */
    public function category(): static
    {
        return $this->state(fn () => [
            'type' => ChannelType::GuildCategory,
        ]);
    }

    /**
     * Indicate the channel is a public thread.
     */
    public function thread(): static
    {
        return $this->state(fn () => [
            'type' => ChannelType::PublicThread,
        ]);
    }

    /**
     * Indicate the channel is an unsynced stub holding only its ID.
     */
    public function stub(): static
    {
        return $this->state(fn () => [
            'name' => null,
            'type' => null,
            'position' => 0,
        ]);
    }
}
