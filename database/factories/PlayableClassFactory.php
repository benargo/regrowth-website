<?php

namespace Database\Factories;

use App\Models\PlayableClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayableClass>
 */
class PlayableClassFactory extends Factory
{
    private $classNames = [
        'Death Knight',
        'Demon Hunter',
        'Druid',
        'Hunter',
        'Mage',
        'Monk',
        'Paladin',
        'Priest',
        'Rogue',
        'Shaman',
        'Warlock',
        'Warrior',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1, 12),
            'name' => $this->faker->unique()->randomElement($this->classNames),
        ];
    }
}
