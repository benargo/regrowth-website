<?php

namespace Database\Factories\TBC;

use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TBC\Raid>
 */
class RaidFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
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
}
