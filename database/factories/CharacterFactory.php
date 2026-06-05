<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Illuminate\Database\Eloquent\Factories\Factory;

class CharacterFactory extends Factory
{
    private $characterNames = [
        'Arthas', 'Jaina', 'Thrall', 'Sylvanas', 'Garrosh', 'Tyrande', 'Malfurion', 'Illidan', 'Kael\'thas',
        'Gul\'dan', 'Anduin', 'Varian', 'Vol\'jin', 'Cenarius', 'Kel\'Thuzad', 'Velen', 'Lor\'themar',
        'Anub\'arak', 'Genn Greymane', 'Rexxar', 'Valeera Sanguinar', 'Medivh', 'Tichondrius', 'Alleria', 'Vereesa',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement($this->characterNames),
            'level' => fake()->numberBetween(1, 80),
            'rank_id' => null,
            'playable_class_id' => null,
            'playable_race_id' => null,
            'is_main' => false,
        ];
    }

    /**
     * Indicate that the character is a main character.
     */
    public function main(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_main' => true,
        ]);
    }

    /**
     * Indicate that the character is a loot councillor.
     */
    public function lootCouncillor(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_loot_councillor' => true,
        ]);
    }

    /**
     * Use a unique randomly-generated name instead of the fixed pool, preventing
     * collisions when creating multiple characters in the same test.
     */
    public function withUniqueName(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->unique()->firstName(),
        ]);
    }

    /**
     * Indicate that the character has a guild rank.
     */
    public function withRank(): static
    {
        return $this->state(fn (array $attributes) => [
            'rank_id' => GuildRank::factory(),
        ]);
    }

    /**
     * Indicate that the character has reached the level cap.
     */
    public function reachedLevelCap(): static
    {
        return $this->state(fn (array $attributes) => [
            'reached_level_cap_at' => now(),
        ]);
    }

    /**
     * Indicate that the character has a playable class.
     */
    public function withPlayableClass(?PlayableClass $playableClass = null): static
    {
        return $this->state(fn (array $attributes) => [
            'playable_class_id' => $playableClass ?? PlayableClass::factory(),
        ]);
    }

    /**
     * Indicate that the character has a playable race.
     */
    public function withPlayableRace(?PlayableRace $playableRace = null): static
    {
        return $this->state(fn (array $attributes) => [
            'playable_race_id' => $playableRace ?? PlayableRace::factory(),
        ]);
    }
}
