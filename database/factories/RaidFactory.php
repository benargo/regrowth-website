<?php

namespace Database\Factories;

use App\Enums\RaidBackground;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
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
            'max_players' => fake()->randomElement([10, 25, null]),
            'max_loot_councillors' => null,
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
     * Set the maximum number of loot councillors for the raid.
     */
    public function withLootCouncillors(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'max_loot_councillors' => $count,
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
        return $this->afterCreating(function (Raid $raid) use ($count): void {
            $items = Item::factory()->count($count)->trashDrop()->create();
            $this->attachItems($raid, $items->pluck('id')->all());
        });
    }

    /**
     * Create the raid with a single item carrying the given number of comments.
     */
    public function withComments(int $count = 3): static
    {
        return $this->afterCreating(function (Raid $raid) use ($count): void {
            $item = Item::factory()
                ->has(Comment::factory()->count($count), 'comments')
                ->create();

            $this->attachItems($raid, [$item->id]);
        });
    }

    /**
     * Attach the given items to the raid without detaching existing ones.
     *
     * @param  array<int, int>  $itemIds
     */
    private function attachItems(Raid $raid, array $itemIds): void
    {
        $raid->items()->syncWithoutDetaching($itemIds);
    }

    /**
     * Indicate that the raid has a background CSS class set.
     */
    public function withBackground(?RaidBackground $background = null): static
    {
        return $this->state(fn (array $attributes) => [
            'background_css_class' => $background ?? fake()->randomElement(RaidBackground::cases()),
        ]);
    }
}
