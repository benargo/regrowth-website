<?php

namespace Database\Factories\GuildRosterManager;

use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Models\GuildRosterManager\Upload;
use App\Models\GuildRosterManager\UploadRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UploadRow>
 */
class UploadRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'upload_id' => Upload::factory(),
            'character_id' => null,
            'guid' => 'Player-'.fake()->unique()->numberBetween(1000, 9999999),
            'name' => fake()->firstName(),
            'status' => RowStatus::Processed,
            'reason' => RowReason::Updated,
            'message' => null,
            'is_latest' => true,
        ];
    }
}
