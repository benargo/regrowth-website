<?php

namespace Database\Factories\GuildRosterManager;

use App\Contracts\Data\GuildRosterManager\RosterEntryData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\RosterEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterEntry>
 */
class RosterEntryFactory extends Factory
{
    use BuildsRosterEntryData;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $guid = 'Player-'.fake()->unique()->numberBetween(1, 9999).'-'.strtoupper(fake()->unique()->bothify('????????'));

        return [
            'game_version_id' => GameVersion::factory(),
            'kind' => RosterEntryKind::Member,
            'key' => $guid,
            'payload' => fn (array $attributes): RosterEntryData => $this->rosterEntryData($attributes['kind'], $attributes['key'], $attributes['epoch']),
            'fingerprint' => hash('sha256', $guid),
            'epoch' => 0,
            'revision' => 1,
        ];
    }
}
