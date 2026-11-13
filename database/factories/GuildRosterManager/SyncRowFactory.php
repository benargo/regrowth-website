<?php

namespace Database\Factories\GuildRosterManager;

use App\Contracts\Data\GuildRosterManager\RosterEntryData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\GuildRosterManager\SyncRow;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncRow>
 */
class SyncRowFactory extends Factory
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
            'upload_id' => Upload::factory()->staging(),
            'kind' => RosterEntryKind::Member,
            'key' => $guid,
            'payload' => fn (array $attributes): RosterEntryData => $this->rosterEntryData($attributes['kind'], $attributes['key'], $attributes['epoch']),
            'epoch' => 0,
        ];
    }
}
