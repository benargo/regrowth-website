<?php

namespace Database\Factories\Raids;

use App\Models\Raids\Report;
use App\Models\GuildTag;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
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
        ];
    }

    /**
     * Indicate that the report has no zone.
     */
    public function withoutZone(): static
    {
        return $this->state(fn (array $attributes) => [
            'zone_id' => null,
        ]);
    }

    /**
     * Indicate that the report belongs to a guild tag.
     */
    public function withGuildTag(?GuildTag $guildTag = null): static
    {
        return $this->state(fn (array $attributes) => [
            'guild_tag_id' => $guildTag?->id ?? GuildTag::factory(),
        ]);
    }

    /**
     * Indicate that the report has no guild tag.
     */
    public function withoutGuildTag(): static
    {
        return $this->state(fn (array $attributes) => [
            'guild_tag_id' => null,
        ]);
    }

    /**
     * Indicate that the report has a specific zone.
     */
    public function withZone(?Zone $zone = null): static
    {
        return $this->state(fn (array $attributes) => [
            'zone_id' => $zone?->id ?? Zone::factory(),
        ]);
    }
}
