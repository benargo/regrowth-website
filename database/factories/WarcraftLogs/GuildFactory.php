<?php

namespace Database\Factories\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
            'logs_url' => null,
        ];
    }

    /**
     * Give the guild a Warcraft Logs page URL, an https URL by default.
     */
    public function withLogsUrl(?string $url = null): static
    {
        return $this->state(fn (): array => [
            'logs_url' => $url ?? Str::replaceStart('http://', 'https://', fake()->url()),
        ]);
    }
}
