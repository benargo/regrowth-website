<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscordRole>
 */
class DiscordRoleFactory extends Factory
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
            'name' => fake()->word(),
            'position' => fake()->unique()->numberBetween(100, 999),
            'can_comment_on_loot_items' => false,
        ];
    }

    /**
     * Indicate the role is Officer.
     */
    public function officer(): static
    {
        return $this->state(fn () => [
            'id' => '829021769448816691',
            'name' => 'Officer',
            'position' => 1,
        ]);
    }

    /**
     * Indicate the role is Loot Councillor.
     */
    public function lootCouncillor(): static
    {
        return $this->state(fn () => [
            'id' => '1467994755953852590',
            'name' => 'Loot Councillor',
            'position' => 2,
        ]);
    }

    /**
     * Indicate the role is Raider.
     */
    public function raider(): static
    {
        return $this->state(fn () => [
            'id' => '1265247017215594496',
            'name' => 'Raider',
            'position' => 3,
        ]);
    }

    /**
     * Indicate the role is Member.
     */
    public function member(): static
    {
        return $this->state(fn () => [
            'id' => '829022020301094922',
            'name' => 'Member',
            'position' => 4,
        ]);
    }

    /**
     * Indicate the role is Guest.
     */
    public function guest(): static
    {
        return $this->state(fn () => [
            'id' => '829022292590985226',
            'name' => 'Guest',
            'position' => 5,
        ]);
    }
}
