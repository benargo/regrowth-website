<?php

namespace Database\Factories\WarcraftLogs;

use App\Models\WarcraftLogs\Zone;
use App\Services\WarcraftLogs\ValueObjects\Difficulty;
use App\Services\WarcraftLogs\ValueObjects\Expansion;
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
            'name' => fake()->words(3, true),
            'difficulties' => [
                new Difficulty(id: 3, name: 'Normal', sizes: [10, 25]),
                new Difficulty(id: 4, name: 'Heroic', sizes: [10, 25]),
            ],
            'expansion' => new Expansion(id: fake()->numberBetween(1, 10), name: fake()->word()),
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
    public function withExpansion(Expansion $expansion): static
    {
        return $this->state(fn (array $attributes) => [
            'expansion' => $expansion,
        ]);
    }

    /**
     * Set specific difficulties on the zone.
     *
     * @param  array<Difficulty>  $difficulties
     */
    public function withDifficulties(array $difficulties): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulties' => $difficulties,
        ]);
    }
}
