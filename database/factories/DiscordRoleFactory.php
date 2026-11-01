<?php

namespace Database\Factories;

use App\Models\DiscordRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordRole>
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
            'primary_color' => fake()->hexColor(),
            'is_visible' => false,
            'grants_permissions' => false,
        ];
    }

    /**
     * Indicate the role is visible.
     */
    public function visible(): static
    {
        return $this->state(fn () => [
            'is_visible' => true,
        ]);
    }

    /**
     * Indicate the role grants permissions.
     */
    public function grantsPermissions(): static
    {
        return $this->state(fn () => [
            'grants_permissions' => true,
        ]);
    }

    /**
     * Indicate the role is Officer.
     */
    public function officer(): static
    {
        return $this->state(fn () => [
            'id' => '829021769448816691',
            'name' => 'Officer',
            'primary_color' => 'aa3b3b',
            'position' => 5,
            'is_visible' => true,
            'grants_permissions' => true,
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
            'primary_color' => 'a335ee',
            'position' => 4,
            'is_visible' => true,
            'grants_permissions' => true,
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
            'primary_color' => 'e67e22',
            'position' => 3,
            'is_visible' => true,
            'grants_permissions' => true,
        ]);
    }

    /**
     * Indicate the role is Member.
     */
    public function member(): static
    {
        return $this->state(fn () => [
            'name' => 'Member',
            'primary_color' => '1f8b4c',
            'position' => 2,
            'is_visible' => true,
            'grants_permissions' => true,
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
            'primary_color' => '9b59b6',
            'position' => 1,
            'is_visible' => true,
            'grants_permissions' => false,
        ]);
    }
}
