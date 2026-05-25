<?php

namespace Database\Factories;

use App\Enums\CharacterRole;
use App\Models\CharacterSpecialisation;
use App\Models\PlayableClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterSpecialisation>
 */
class CharacterSpecialisationFactory extends Factory
{
    private array $specNames = [
        'Protection', 'Arms', 'Fury',
        'Holy', 'Discipline', 'Shadow',
        'Balance', 'Feral', 'Restoration',
        'Beast Mastery', 'Marksmanship', 'Survival',
        'Arcane', 'Fire', 'Frost',
        'Assassination', 'Outlaw', 'Subtlety',
        'Enhancement', 'Elemental',
        'Affliction', 'Demonology', 'Destruction',
        'Brewmaster', 'Mistweaver', 'Windwalker',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'playable_class_id' => PlayableClass::factory(),
            'role' => fake()->randomElement(CharacterRole::cases()),
            'name' => fake()->unique()->randomElement($this->specNames),
        ];
    }

    /**
     * Set the specialisation role to tank.
     */
    public function tank(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => CharacterRole::tank,
        ]);
    }

    /**
     * Set the specialisation role to healer.
     */
    public function healer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => CharacterRole::healer,
        ]);
    }

    /**
     * Set the specialisation role to damage.
     */
    public function damage(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => CharacterRole::damage,
        ]);
    }
}
