<?php

namespace Database\Factories\GuildRosterManager;

use App\Enums\GuildRosterManager\UploadOutcome;
use App\Enums\GuildRosterManager\UploadSource;
use App\Models\DesktopClient;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Upload>
 */
class UploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_version_id' => GameVersion::factory(),
            'user_id' => User::factory(),
            'source' => UploadSource::Manual,
            'outcome' => UploadOutcome::Received,
            'captured_at' => now(),
            'member_count' => fake()->numberBetween(1, 500),
        ];
    }

    /**
     * Indicate that the upload was received from a desktop client.
     */
    public function desktop(?string $idempotencyKey = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => UploadSource::Desktop,
            'idempotency_key' => $idempotencyKey ?? hash('sha256', fake()->uuid()),
        ]);
    }

    /**
     * Indicate that the upload is a desktop sync still being staged.
     */
    public function staging(?DesktopClient $desktopClient = null): static
    {
        return $this->desktop()->state(fn (array $attributes) => [
            'outcome' => UploadOutcome::Staging,
            'base_revision' => 0,
            'captured_at' => now(),
            'expires_at' => now()->addHour(),
            'ticket_expires_at' => now()->addMinutes(5),
            'member_count' => 0,
            'desktop_client_id' => $desktopClient?->id
                ?? fn (array $resolved) => DesktopClient::factory()->create(['user_id' => $resolved['user_id']])->id,
        ]);
    }

    /**
     * Indicate that the desktop sync was committed as the given revision.
     */
    public function committed(int $revision): static
    {
        return $this->desktop()->state(fn (array $attributes) => [
            'outcome' => UploadOutcome::Received,
            'revision' => $revision,
        ]);
    }

    /**
     * Indicate the upload's outcome.
     */
    public function withOutcome(UploadOutcome $outcome): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => $outcome,
        ]);
    }
}
