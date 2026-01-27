<?php

namespace Database\Factories\TBC;

use App\Models\TBC\Boss;
use App\Models\TBC\Raid;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TBC\Boss>
 */
class BossFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Boss::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Attumen the Huntsman', 'Moroes', 'Maiden of Virtue', 'Opera Event', 'The Curator', 'Shade of Aran', 'Netherspite', 'Prince Malchezaar', 'Illidan Stormrage', 'Kil\'jaeden']),
            'raid_id' => Raid::factory(),
            'encounter_order' => fake()->numberBetween(1, 12),
        ];
    }

    /**
     * Set the encounter order.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'encounter_order' => $order,
        ]);
    }
}
