<?php

namespace Database\Factories\WarcraftLogs;

use App\Models\WarcraftLogs\Report;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WarcraftLogs\Report>
 */
class ReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('-30 days', 'now');
        $endTime = Carbon::instance($startTime)->addHours(rand(2, 5));

        return [
            'code' => fake()->unique()->regexify('[A-Za-z0-9]{16}'),
            'title' => fake()->words(3, true),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'zone_id' => fake()->optional(0.7)->numberBetween(1000, 1100),
            'zone_name' => fn (array $attrs) => $attrs['zone_id'] ? fake()->word() : null,
        ];
    }

    /**
     * Indicate that the report has no zone.
     */
    public function withoutZone(): static
    {
        return $this->state(fn (array $attributes) => [
            'zone_id' => null,
            'zone_name' => null,
        ]);
    }

    /**
     * Indicate that the report has a specific zone.
     */
    public function withZone(int $zoneId, string $zoneName): static
    {
        return $this->state(fn (array $attributes) => [
            'zone_id' => $zoneId,
            'zone_name' => $zoneName,
        ]);
    }
}
