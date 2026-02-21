<?php

namespace Database\Factories;

use App\Models\DiscordRole;
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
            'is_admin' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate the user is an Officer.
     */
    public function officer(): static
    {
        return $this->afterCreating(function ($user) {
            $user->discordRoles()->syncWithoutDetaching([
                DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 5])->id,
            ]);
        });
    }

    /**
     * Indicate the user is a Raider.
     */
    public function raider(): static
    {
        return $this->afterCreating(function ($user) {
            $user->discordRoles()->syncWithoutDetaching([
                DiscordRole::firstOrCreate(['id' => '1265247017215594496'], ['name' => 'Raider', 'position' => 3])->id,
            ]);
        });
    }

    /**
     * Indicate the user is a Member.
     */
    public function member(): static
    {
        return $this->afterCreating(function ($user) {
            $user->discordRoles()->syncWithoutDetaching([
                DiscordRole::firstOrCreate(['id' => '829022020301094922'], ['name' => 'Member', 'position' => 2])->id,
            ]);
        });
    }

    /**
     * Indicate the user is a Loot Councillor.
     */
    public function lootCouncillor(): static
    {
        return $this->afterCreating(function ($user) {
            $user->discordRoles()->syncWithoutDetaching([
                DiscordRole::firstOrCreate(['id' => '1467994755953852590'], ['name' => 'Loot Councillor', 'position' => 4])->id,
            ]);
        });
    }

    /**
     * Indicate the user is a Guest.
     */
    public function guest(): static
    {
        return $this->afterCreating(function ($user) {
            $user->discordRoles()->syncWithoutDetaching([
                DiscordRole::firstOrCreate(['id' => '829022292590985226'], ['name' => 'Guest', 'position' => 1])->id,
            ]);
        });
    }

    /**
     * Indicate the user has the given roles.
     */
    public function withRoles(array $roleIds): static
    {
        return $this->afterCreating(function ($user) use ($roleIds) {
            $user->discordRoles()->syncWithoutDetaching($roleIds);
        });
    }

    /**
     * Indicate the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    /**
     * Indicate the user has no recognized guild roles.
     */
    public function noRoles(): static
    {
        return $this->afterCreating(function ($user) {
            $user->discordRoles()->detach();
        });
    }
}
