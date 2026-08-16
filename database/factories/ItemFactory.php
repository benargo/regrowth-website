<?php

namespace Database\Factories;

use App\Enums\ItemQuality;
use App\Models\Boss;
use App\Models\Item;
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
            'boss_id' => null,
            'name' => null,
            'quality' => fake()->randomElement(ItemQuality::cases()),
            'group' => fake()->optional(0.5)->randomElement(['Tokens', 'Weapons', 'Armor', 'Trinkets', 'Rings']),
            'notes' => null,
        ];
    }

    /**
     * Attach the item to a single raid.
     */
    public function withRaid(?Raid $raid = null): static
    {
        return $this->afterCreating(function (Item $item) use ($raid): void {
            $item->raids()->syncWithoutDetaching([($raid ?? Raid::factory()->create())->id]);
        });
    }

    /**
     * Attach the item to several raids.
     *
     * @param  array<int, Raid>  $raids
     */
    public function inRaids(array $raids): static
    {
        return $this->afterCreating(function (Item $item) use ($raids): void {
            $item->raids()->syncWithoutDetaching(collect($raids)->pluck('id')->all());
        });
    }

    /**
     * Indicate that the item drops from a specific boss, in that boss's raid.
     */
    public function fromBoss(?Boss $boss = null): static
    {
        $boss = $boss ?? Boss::factory()->create();

        return $this->state(fn (array $attributes) => [
            'boss_id' => $boss->id,
        ])->afterCreating(function (Item $item) use ($boss): void {
            $item->raids()->syncWithoutDetaching([$boss->raid_id]);
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
     * Set notes for the item.
     */
    public function withNotes(?string $notes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes ?? fake()->sentence(),
        ]);
    }

    /**
     * Set the item quality.
     */
    public function withQuality(?ItemQuality $quality = null): static
    {
        return $this->state(fn (array $attributes) => [
            'quality' => $quality ?? fake()->randomElement(ItemQuality::cases()),
        ]);
    }
}
