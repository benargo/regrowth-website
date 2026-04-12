<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\GuildRank;
use App\Services\Blizzard\ValueObjects\PlayableClass;
use App\Services\Blizzard\ValueObjects\PlayableRace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'rank_id' => null,
            'is_main' => false,
            'playable_class' => null,
            'playable_race' => null,
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
     *
     * Builds a PlayableClass value object from a minimal fake API response so
     * the AsPlayableClass cast's set() path runs. Tests using this state must
     * mock BlizzardService::getPlayableClassMedia and MediaService::get.
     */
    public function withPlayableClass(int $classId = 1, string $name = 'Warrior'): static
    {
        return $this->state(fn (array $attributes) => [
            'playable_class' => PlayableClass::fromApiResponse([
                'id' => $classId,
                'name' => $name,
                'gender_name' => ['male' => $name, 'female' => $name],
                'power_type' => [],
                'media' => [],
                'pvp_talent_slots' => [],
                'playable_races' => [],
            ]),
        ]);
    }

    /**
     * Indicate that the character has a playable race.
     *
     * Builds a PlayableRace value object from a minimal fake API response so
     * the AsPlayableRace cast's set() path runs.
     */
    public function withPlayableRace(int $raceId = 1, string $name = 'Human'): static
    {
        return $this->state(fn (array $attributes) => [
            'playable_race' => PlayableRace::fromApiResponse([
                'id' => $raceId,
                'name' => $name,
                'gender_name' => ['male' => $name, 'female' => $name],
                'faction' => [],
                'is_selectable' => true,
                'is_allied_race' => false,
                'playable_classes' => [],
                'racial_spells' => [],
            ]),
        ]);
    }
}
