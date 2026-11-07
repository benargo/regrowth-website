<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\FindOrImportPlayableRace;
use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceRequest;
use App\Models\PlayableRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class FindOrImportPlayableRaceTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    #[Group('happy-path')]
    #[Test]
    public function it_returns_an_existing_race_without_calling_blizzard(): void
    {
        PlayableRace::factory()->create(['id' => 7, 'name' => 'Gnomish']);
        $this->mockGetPlayableRace();
        $this->applyBlizzardMocks();

        $result = FindOrImportPlayableRace::run(7, BlizzardNamespace::FOREVER);

        $this->assertSame('Gnomish', $result->name);
        Saloon::assertNotSent(GetPlayableRaceRequest::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_creates_a_missing_race_with_its_faction(): void
    {
        $this->mockGetPlayableRace();
        $this->applyBlizzardMocks();

        $race = FindOrImportPlayableRace::run(7, BlizzardNamespace::FOREVER);

        $this->assertDatabaseHas('playable_races', ['id' => 7, 'name' => 'Gnome']);
        $this->assertSame(Faction::ALLIANCE, $race->faction);
        Saloon::assertSent(
            fn ($request, $response) => $request instanceof GetPlayableRaceRequest
                && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === 'static-forever-eu',
        );
    }

    #[Group('edge-case')]
    #[Test]
    public function it_matches_a_faction_type_in_any_case(): void
    {
        $this->mockGetPlayableRace(responseData: ['faction' => ['type' => 'horde', 'name' => 'Horde']]);
        $this->applyBlizzardMocks();

        $race = FindOrImportPlayableRace::run(7, BlizzardNamespace::FOREVER);

        $this->assertSame(Faction::HORDE, $race->faction);
    }

    /**
     * @param  array<string, mixed>  $responseData
     */
    #[DataProvider('unsupportedFactions')]
    #[Group('edge-case')]
    #[Test]
    public function it_does_not_import_a_race_without_a_supported_faction(array $responseData): void
    {
        $this->mockGetPlayableRace(responseData: $responseData);
        $this->applyBlizzardMocks();

        $race = FindOrImportPlayableRace::run(7, BlizzardNamespace::FOREVER);

        $this->assertNull($race);
        $this->assertDatabaseCount('playable_races', 0);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_throws_when_blizzard_cannot_return_the_race(): void
    {
        $this->mockGetPlayableRace(status: 500);
        $this->applyBlizzardMocks();

        try {
            FindOrImportPlayableRace::run(7, BlizzardNamespace::FOREVER);
            $this->fail('Expected a BlizzardRequestException.');
        } catch (BlizzardRequestException) {
            $this->assertDatabaseMissing('playable_races', ['id' => 7]);
        }
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function unsupportedFactions(): array
    {
        return [
            'neutral' => [['faction' => ['type' => 'NEUTRAL', 'name' => 'Neutral']]],
            'unknown' => [['faction' => ['type' => 'CONTESTED', 'name' => 'Contested']]],
            'missing' => [[]],
        ];
    }
}
