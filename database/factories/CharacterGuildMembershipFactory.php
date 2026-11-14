<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterGuildMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterGuildMembership>
 */
class CharacterGuildMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'joined_on' => now()->subDays(fake()->numberBetween(30, 365))->toDateString(),
            'left_on' => null,
            'verified' => false,
        ];
    }
}
