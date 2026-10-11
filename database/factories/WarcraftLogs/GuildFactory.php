<?php

namespace Database\Factories\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Guild>
 */
class GuildFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Guild::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 9999999),
            'namespace' => fake()->randomElement(WarcraftLogsNamespace::cases()),
        ];
    }
}
