<?php

namespace Database\Factories\TBC;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\Raids\Event;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Raid>
 */
class RaidFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Raid::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Karazhan', 'Gruul\'s Lair', 'Magtheridon\'s Lair', 'Serpentshrine Cavern', 'Tempest Keep', 'Black Temple', 'Sunwell Plateau']),
            'difficulty' => fake()->randomElement(['Normal', 'Heroic']),
            'phase_id' => Phase::factory(),
            'max_players' => fake()->randomElement([10, 25]),
        ];
    }

    /**
     * Indicate that the raid is a 10-player raid.
     */
    public function tenPlayer(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_players' => 10,
        ]);
    }

    /**
     * Indicate that the raid is a 25-player raid.
     */
    public function twentyFivePlayer(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_players' => 25,
        ]);
    }

    /**
     * Indicate that the raid is normal difficulty.
     */
    public function normal(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty' => 'Normal',
        ]);
    }

    /**
     * Indicate that the raid is heroic difficulty.
     */
    public function heroic(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty' => 'Heroic',
        ]);
    }

    /**
     * Create the raid with bosses attached.
     */
    public function withBosses(int $count = 3): static
    {
        return $this->has(Boss::factory()->count($count), 'bosses');
    }

    /**
     * Create the raid with items attached (trash drops).
     */
    public function withItems(int $count = 3): static
    {
        return $this->has(Item::factory()->count($count)->trashDrop(), 'items');
    }

    /**
     * Create the raid with items and comments attached.
     */
    public function withComments(int $count = 3): static
    {
        return $this->has(
            Item::factory()->has(Comment::factory()->count($count), 'comments'),
            'items'
        );
    }

    /**
     * Create the raid with events attached.
     */
    public function withEvents(int $count = 3): static
    {
        return $this->has(Event::factory()->count($count), 'events');
    }
}
