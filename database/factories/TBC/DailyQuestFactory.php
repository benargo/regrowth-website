<?php

namespace Database\Factories\TBC;

use App\Models\TBC\DailyQuest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TBC\DailyQuest>
 */
class DailyQuestFactory extends Factory
{
    protected $model = DailyQuest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['Cooking', 'Dungeon', 'Fishing', 'PvP']),
            'instance' => null,
            'mode' => null,
            'rewards' => [
                ['item_id' => fake()->numberBetween(20000, 40000), 'quantity' => fake()->numberBetween(1, 5)],
            ],
        ];
    }

    public function cooking(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Cooking',
            'instance' => null,
            'mode' => null,
            'rewards' => [
                ['item_id' => 33844, 'quantity' => 1], // Barrel of Fish
                ['item_id' => 33857, 'quantity' => 1], // Crate of Meat
            ],
        ]);
    }

    public function fishing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Fishing',
            'instance' => null,
            'mode' => null,
            'rewards' => [
                ['item_id' => 34863, 'quantity' => 1], // Bag of Fishing Treasures
            ],
        ]);
    }

    public function dungeon(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Dungeon',
            'instance' => fake()->randomElement([
                'The Arcatraz', 'The Steamvault', 'Shadow Labyrinth',
                'The Black Morass', 'The Shattered Halls',
                'The Botanica', 'The Mechanar',
            ]),
            'mode' => 'Normal',
            'rewards' => [
                ['item_id' => 29460, 'quantity' => 1], // Ethereum Prison Key
            ],
        ]);
    }

    public function instance(): static
    {
        return $this->dungeon();
    }

    public function heroic(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'Dungeon',
            'instance' => fake()->randomElement([
                'The Underbog', 'The Botanica', 'The Black Morass',
                'The Shattered Halls', 'The Blood Furnace', 'Shadow Labyrinth',
                'Hellfire Ramparts', 'The Mechanar', 'Mana-Tombs',
                'Old Hillsbrad Foothills', 'Auchenai Crypts', 'Sethekk Halls',
                'The Slave Pens', 'The Arcatraz', 'The Steamvault',
            ]),
            'mode' => 'Heroic',
            'rewards' => [
                ['item_id' => 29434, 'quantity' => 2], // Badge of Justice (2)
            ],
        ]);
    }

    public function pvp(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'PvP',
            'instance' => fake()->randomElement([
                'Alterac Valley', 'Arathi Basin', 'Eye of the Storm', 'Warsong Gulch',
            ]),
            'mode' => null,
            'rewards' => [
                ['item_id' => fake()->randomElement([20560, 20559, 29024, 20558]), 'quantity' => 3],
            ],
        ]);
    }
}
