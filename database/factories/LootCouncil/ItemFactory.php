<?php

namespace Database\Factories\LootCouncil;

use App\Models\LootCouncil\Item;
use App\Models\TBC\Boss;
use App\Models\TBC\Raid;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LootCouncil\Item>
 */
class ItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Item::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'raid_id' => Raid::factory(),
            'boss_id' => null,
            'group' => fake()->optional(0.5)->randomElement(['Tokens', 'Weapons', 'Armor', 'Trinkets', 'Rings']),
        ];
    }

    /**
     * Indicate that the item drops from a specific boss.
     */
    public function fromBoss(?Boss $boss = null): static
    {
        return $this->state(function (array $attributes) use ($boss) {
            $boss = $boss ?? Boss::factory()->create(['raid_id' => $attributes['raid_id']]);

            return [
                'boss_id' => $boss->id,
                'raid_id' => $boss->raid_id,
            ];
        });
    }

    /**
     * Indicate that the item is a trash drop (no boss).
     */
    public function trashDrop(): static
    {
        return $this->state(fn (array $attributes) => [
            'boss_id' => null,
        ]);
    }

    /**
     * Set the item group.
     */
    public function inGroup(string $group): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => $group,
        ]);
    }
}
