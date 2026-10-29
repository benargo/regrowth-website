<?php

namespace Database\Factories;

use App\Models\GameVersion;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Zone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

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
     * Give the report a game version the way it derives one: the version's
     * guild, a tag in that guild, and a start time the day after the
     * version's release. The version must have a Warcraft Logs guild.
     */
    public function forGameVersion(GameVersion $gameVersion): static
    {
        if ($gameVersion->warcraft_logs_guild_id === null) {
            throw new LogicException('forGameVersion() needs a game version with a Warcraft Logs guild.');
        }

        $startTime = $gameVersion->release_date->copy()->addDay();

        return $this->state(fn (array $attributes) => [
            'warcraft_logs_guild_id' => $gameVersion->warcraft_logs_guild_id,
            'guild_tag_id' => GuildTag::factory()->state(['warcraft_logs_guild_id' => $gameVersion->warcraft_logs_guild_id]),
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addHours(3),
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

    /**
     * Indicate that the report was fetched from a Warcraft Logs guild, a new
     * one when none is given.
     */
    public function forGuild(?Guild $guild = null): static
    {
        return $this->state(fn (array $attributes) => [
            'warcraft_logs_guild_id' => $guild?->id ?? Guild::factory(),
        ]);
    }
}
