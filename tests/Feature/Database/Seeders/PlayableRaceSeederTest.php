<?php

namespace Tests\Feature\Database\Seeders;

use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceIndexRequest;
use App\Models\PlayableRace;
use Database\Seeders\PlayableRaceSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class PlayableRaceSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeder_creates_playable_races_from_api(): void
    {
        $this->fakeSaloon();

        $this->runSeeder();

        $this->assertDatabaseCount('playable_races', 2);
        $this->assertDatabaseHas('playable_races', ['id' => 1, 'name' => 'Human']);
        $this->assertDatabaseHas('playable_races', ['id' => 2, 'name' => 'Orc']);
    }

    #[Test]
    public function seeder_updates_existing_playable_race_without_duplicating(): void
    {
        $this->fakeSaloon();

        PlayableRace::factory()->create(['id' => 1, 'name' => 'Old Name']);

        $this->runSeeder();

        $this->assertDatabaseCount('playable_races', 2);
        $this->assertDatabaseHas('playable_races', ['id' => 1, 'name' => 'Human']);
    }

    #[Test]
    public function seeder_does_nothing_when_races_list_is_empty(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetPlayableRaceIndexRequest::class => MockResponse::make(
                body: ['races' => []],
                status: 200,
            ),
        ]);

        $this->runSeeder();

        $this->assertDatabaseCount('playable_races', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeRacesResponse(array $races = []): array
    {
        return ['races' => $races ?: [
            ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
            ['key' => ['href' => 'https://example.test/race/2'], 'name' => 'Orc', 'id' => 2],
        ]];
    }

    private function fakeSaloon(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetPlayableRaceIndexRequest::class => MockResponse::make(
                body: $this->makeRacesResponse(),
                status: 200,
            ),
        ]);
    }

    private function runSeeder(): void
    {
        Model::unguarded(fn () => app(PlayableRaceSeeder::class)->run());
    }
}
