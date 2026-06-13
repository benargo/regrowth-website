<?php

namespace Tests\Feature\Database\Seeders;

use App\Enums\Faction;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceIndexRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceRequest;
use App\Models\PlayableRace;
use Database\Seeders\PlayableRaceSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
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
        $this->assertDatabaseHas('playable_races', ['id' => 1, 'name' => 'Human', 'faction' => Faction::ALLIANCE->value]);
        $this->assertDatabaseHas('playable_races', ['id' => 2, 'name' => 'Orc', 'faction' => Faction::HORDE->value]);
    }

    #[Test]
    public function seeder_updates_existing_playable_race_without_duplicating(): void
    {
        $this->fakeSaloon();

        PlayableRace::factory()->create(['id' => 1, 'name' => 'Old Name']);

        $this->runSeeder();

        $this->assertDatabaseCount('playable_races', 2);
        $this->assertDatabaseHas('playable_races', ['id' => 1, 'name' => 'Human', 'faction' => Faction::ALLIANCE->value]);
    }

    #[Test]
    public function seeder_stores_neutral_faction_when_faction_is_absent_from_api(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetPlayableRaceIndexRequest::class => MockResponse::make(
                body: ['races' => [
                    ['key' => ['href' => 'https://example.test/race/9'], 'name' => 'Goblin', 'id' => 9],
                ]],
                status: 200,
            ),
            GetPlayableRaceRequest::class => MockResponse::make(
                body: $this->makeRaceResponse(9, 'Goblin'),
                status: 200,
            ),
        ]);

        $this->runSeeder();

        $this->assertDatabaseHas('playable_races', ['id' => 9, 'name' => 'Goblin', 'faction' => Faction::NEUTRAL->value]);
    }

    #[Test]
    public function seeder_outputs_a_line_per_race_to_the_console(): void
    {
        $this->fakeSaloon();

        $command = $this->createMock(Command::class);
        $command->expects($this->exactly(2))
            ->method('line')
            ->with($this->matchesRegularExpression('/✓.*\[(?:1|2)\].*(?:Human|Orc)/'));

        $this->runSeeder($command);
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

    /**
     * @return array<string, mixed>
     */
    private function makeRaceResponse(int $id, string $name, ?Faction $faction = null): array
    {
        $data = [
            'id' => $id,
            'name' => $name,
            '_links' => ['self' => ['href' => "https://example.test/race/{$id}"]],
        ];

        if ($faction !== null) {
            $data['faction'] = ['type' => $faction->name, 'name' => $faction->value];
        }

        return $data;
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
            GetPlayableRaceRequest::class => function (PendingRequest $request): MockResponse {
                $raceId = (int) last(explode('/', parse_url($request->getUrl(), PHP_URL_PATH)));

                return match ($raceId) {
                    1 => MockResponse::make(body: $this->makeRaceResponse(1, 'Human', Faction::ALLIANCE), status: 200),
                    2 => MockResponse::make(body: $this->makeRaceResponse(2, 'Orc', Faction::HORDE), status: 200),
                    default => MockResponse::make(body: $this->makeRaceResponse($raceId, 'Unknown'), status: 200),
                };
            },
        ]);
    }

    private function runSeeder(?Command $command = null): void
    {
        Model::unguarded(function () use ($command) {
            $seeder = app(PlayableRaceSeeder::class);
            $seeder->setCommand($command ?? $this->createStub(Command::class));
            $seeder->run();
        });
    }
}
