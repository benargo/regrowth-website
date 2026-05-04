<?php

namespace Database\Factories\LootCouncil;

use App\Models\Boss;
use App\Models\LootCouncil\Item;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
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
            'name' => null,
            'icon' => null,
            'group' => fake()->optional(0.5)->randomElement(['Tokens', 'Weapons', 'Armor', 'Trinkets', 'Rings']),
            'notes' => null,
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

    /**
     * Set the item name.
     */
    public function withName(?string $name = null): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name ?? fake()->words(3, true),
        ]);
    }

    /**
     * Set the icon media data for the item.
     *
     * @param  array{id: int, assets: array<int, array{key: string, value: string, file_data_id: int}>}|null  $icon
     */
    public function withIcon(?array $icon = null): static
    {
        return $this->state(fn (array $attributes) => [
            'icon' => $icon ?? [
                'id' => fake()->randomNumber(6),
                'assets' => [
                    [
                        'key' => 'icon',
                        'value' => fake()->imageUrl(),
                        'file_data_id' => fake()->randomNumber(6),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Set notes for the item.
     */
    public function withNotes(?string $notes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes ?? fake()->sentence(),
        ]);
    }
}
