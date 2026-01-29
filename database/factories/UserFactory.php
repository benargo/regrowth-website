<?php

namespace Database\Factories;

use App\Enums\DiscordRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
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
            'username' => fake()->userName(),
            'discriminator' => '0',
            'nickname' => fake()->optional(0.7)->firstName(),
            'avatar' => fake()->optional(0.8)->md5(),
            'guild_avatar' => fake()->optional(0.8)->md5(),
            'banner' => null,
            'roles' => [(string) DiscordRole::Member->value],
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate the user is an Officer.
     */
    public function officer(): static
    {
        return $this->state(fn (array $attributes) => [
            'roles' => [(string) DiscordRole::Officer->value],
        ]);
    }

    /**
     * Indicate the user is a Raider.
     */
    public function raider(): static
    {
        return $this->state(fn (array $attributes) => [
            'roles' => [(string) DiscordRole::Raider->value],
        ]);
    }

    /**
     * Indicate the user is a Member.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'roles' => [(string) DiscordRole::Member->value],
        ]);
    }

    /**
     * Indicate the user is a Guest.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'roles' => [(string) DiscordRole::Guest->value],
        ]);
    }

    /**
     * Indicate the user has multiple roles.
     */
    public function withRoles(array $roles): static
    {
        return $this->state(fn (array $attributes) => [
            'roles' => $roles,
        ]);
    }

    /**
     * Indicate the user has no recognized guild roles.
     */
    public function noRoles(): static
    {
        return $this->state(fn (array $attributes) => [
            'roles' => [],
        ]);
    }
}
