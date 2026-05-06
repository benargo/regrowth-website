<?php

namespace Database\Factories;

use App\Models\Zone;
use App\Services\WarcraftLogs\ValueObjects\DifficultyData;
use App\Services\WarcraftLogs\ValueObjects\ExpansionData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Zone::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1000, 1100),
            'name' => fake()->words(3, true),
            'difficulties' => [
                new DifficultyData(id: 3, name: 'Normal', sizes: [10, 25]),
                new DifficultyData(id: 4, name: 'Heroic', sizes: [10, 25]),
            ],
            'expansion' => new ExpansionData(id: 1001, name: 'The Burning Crusade'),
            'is_frozen' => false,
        ];
    }

    /**
     * Indicate that the zone is frozen.
     */
    public function frozen(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_frozen' => true,
        ]);
    }

    /**
     * Indicate that the zone is not frozen.
     */
    public function notFrozen(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_frozen' => false,
        ]);
    }

    /**
     * Set a specific expansion on the zone.
     */
    public function withExpansion(ExpansionData $expansion): static
    {
        return $this->state(fn (array $attributes) => [
            'expansion' => $expansion,
        ]);
    }

    /**
     * Set specific difficulties on the zone.
     *
     * @param  array<DifficultyData>  $difficulties
     */
    public function withDifficulties(array $difficulties): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulties' => $difficulties,
        ]);
    }
}
