<?php

namespace Database\Factories;

use App\Enums\PlayableSpecRole;
use App\Models\PlayableClass;
use App\Models\PlayableSpecialization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayableSpecialization>
 */
class PlayableSpecializationFactory extends Factory
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
            'role' => fake()->randomElement(PlayableSpecRole::cases()),
            'name' => fake()->unique()->randomElement($this->specNames),
        ];
    }

    public function tank(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => PlayableSpecRole::tank,
        ]);
    }

    public function healer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => PlayableSpecRole::healer,
        ]);
    }

    public function damage(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => PlayableSpecRole::damage,
        ]);
    }
}
