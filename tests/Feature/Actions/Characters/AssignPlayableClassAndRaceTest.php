<?php

namespace Tests\Feature\Actions\Characters;

use App\Actions\Characters\AssignPlayableClassAndRace;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassMediaRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceRequest;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class AssignPlayableClassAndRaceTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    #[Group('happy-path')]
    #[Test]
    public function it_assigns_an_existing_class_and_race_and_links_them_to_the_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        PlayableClass::factory()->create(['id' => 5]);
        PlayableRace::factory()->create(['id' => 7]);
        $character = Character::factory()->make();

        AssignPlayableClassAndRace::run($character, $gameVersion, 5, 7);

        $this->assertSame(5, $character->playable_class_id);
        $this->assertSame(7, $character->playable_race_id);
        $this->assertTrue($gameVersion->playableClasses()->whereKey(5)->exists());
        $this->assertTrue($gameVersion->playableRaces()->whereKey(7)->exists());
    }

    #[Group('happy-path')]
    #[Test]
    public function it_imports_a_missing_class_and_race_using_the_game_versions_namespace(): void
    {
        $gameVersion = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $character = Character::factory()->make();
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia();
        $this->mockGetPlayableRace();
        $this->applyBlizzardMocks();

        AssignPlayableClassAndRace::run($character, $gameVersion, 5, 7);

        $this->assertDatabaseHas('playable_classes', ['id' => 5, 'name' => 'Priest']);
        $this->assertDatabaseHas('playable_races', ['id' => 7, 'name' => 'Gnome']);
        foreach ([GetPlayableClassRequest::class, GetPlayableClassMediaRequest::class, GetPlayableRaceRequest::class] as $requestClass) {
            Saloon::assertSent(
                fn ($request, $response) => $request instanceof $requestClass
                    && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === 'static-forever-eu',
            );
        }
    }

    #[Group('edge-case')]
    #[Test]
    public function it_keeps_other_game_version_links(): void
    {
        $versionA = GameVersion::factory()->create();
        $versionB = GameVersion::factory()->create();
        $class = PlayableClass::factory()->create(['id' => 5]);
        PlayableRace::factory()->create(['id' => 7]);
        $versionA->playableClasses()->attach($class->id);

        AssignPlayableClassAndRace::run(Character::factory()->make(), $versionB, 5, 7);

        $this->assertSame(2, $class->gameVersions()->count());
    }

    #[Group('edge-case')]
    #[Test]
    public function it_runs_no_queries_for_a_class_and_race_already_linked_to_the_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->playableClasses()->attach(PlayableClass::factory()->create(['id' => 5]));
        $gameVersion->playableRaces()->attach(PlayableRace::factory()->create(['id' => 7]));
        $gameVersion->load('playableClasses', 'playableRaces');
        $character = Character::factory()->make();

        DB::enableQueryLog();
        AssignPlayableClassAndRace::run($character, $gameVersion, 5, 7);

        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame(5, $character->playable_class_id);
        $this->assertSame(7, $character->playable_race_id);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_refreshes_the_loaded_links_after_linking_a_class_and_race(): void
    {
        $gameVersion = GameVersion::factory()->create();
        PlayableClass::factory()->create(['id' => 5]);
        PlayableRace::factory()->create(['id' => 7]);
        $gameVersion->load('playableClasses', 'playableRaces');

        AssignPlayableClassAndRace::run(Character::factory()->make(), $gameVersion, 5, 7);

        $this->assertTrue($gameVersion->playableClasses->contains(5));
        $this->assertTrue($gameVersion->playableRaces->contains(7));
    }

    // ==================== handle — failed imports ====================

    #[Group('edge-case')]
    #[Test]
    public function it_keeps_the_previous_class_and_logs_when_the_import_fails(): void
    {
        $gameVersion = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $previous = PlayableClass::factory()->create(['id' => 3]);
        $character = Character::factory()->make(['playable_class_id' => $previous->id]);
        $this->mockGetPlayableClass(status: 500);
        $this->mockGetPlayableRace();
        $this->applyBlizzardMocks();
        Log::shouldReceive('warning')->once()->with('Failed to import playable class from Blizzard.', \Mockery::type('array'));

        AssignPlayableClassAndRace::run($character, $gameVersion, 5, 7);

        $this->assertSame(3, $character->playable_class_id);
        $this->assertSame(7, $character->playable_race_id);
        $this->assertSame(0, $gameVersion->playableClasses()->count());
        $this->assertTrue($gameVersion->playableRaces()->whereKey(7)->exists());
    }

    #[Group('edge-case')]
    #[Test]
    public function it_keeps_the_previous_race_and_logs_when_the_import_fails(): void
    {
        $gameVersion = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $previous = PlayableRace::factory()->create(['id' => 3]);
        $character = Character::factory()->make(['playable_race_id' => $previous->id]);
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia();
        $this->mockGetPlayableRace(status: 500);
        $this->applyBlizzardMocks();
        Log::shouldReceive('warning')->once()->with('Failed to import playable race from Blizzard.', \Mockery::type('array'));

        AssignPlayableClassAndRace::run($character, $gameVersion, 5, 7);

        $this->assertSame(3, $character->playable_race_id);
        $this->assertSame(5, $character->playable_class_id);
        $this->assertSame(0, $gameVersion->playableRaces()->count());
        $this->assertTrue($gameVersion->playableClasses()->whereKey(5)->exists());
    }

    #[Group('edge-case')]
    #[Test]
    public function it_attempts_a_failing_class_import_once_per_instance(): void
    {
        $gameVersion = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $this->mockGetPlayableClass(status: 500);
        $this->mockGetPlayableRace();
        $this->applyBlizzardMocks();
        Log::shouldReceive('warning')->once()->with('Failed to import playable class from Blizzard.', \Mockery::type('array'));

        $action = AssignPlayableClassAndRace::make();
        $action->handle(Character::factory()->make(), $gameVersion, 5, 7);
        $action->handle(Character::factory()->make(), $gameVersion, 5, 7);

        Saloon::mockClient()->assertSentCount(1, GetPlayableClassRequest::class);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_attempts_a_failing_race_import_once_per_instance(): void
    {
        $gameVersion = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia();
        $this->mockGetPlayableRace(status: 500);
        $this->applyBlizzardMocks();
        Log::shouldReceive('warning')->once()->with('Failed to import playable race from Blizzard.', \Mockery::type('array'));

        $action = AssignPlayableClassAndRace::make();
        $action->handle(Character::factory()->make(), $gameVersion, 5, 7);
        $action->handle(Character::factory()->make(), $gameVersion, 5, 7);

        Saloon::mockClient()->assertSentCount(1, GetPlayableRaceRequest::class);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_still_assigns_the_race_when_the_class_import_failed(): void
    {
        $gameVersion = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $gameVersion->playableRaces()->attach(PlayableRace::factory()->create(['id' => 7]));
        $firstCharacter = Character::factory()->make(['playable_race_id' => null]);
        $secondCharacter = Character::factory()->make(['playable_race_id' => null]);
        $this->mockGetPlayableClass(status: 500);
        $this->applyBlizzardMocks();

        $action = AssignPlayableClassAndRace::make();
        $action->handle($firstCharacter, $gameVersion, 5, 7);
        $action->handle($secondCharacter, $gameVersion, 5, 7);

        $this->assertSame(7, $firstCharacter->playable_race_id);
        $this->assertSame(7, $secondCharacter->playable_race_id);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_retries_a_failed_import_on_a_new_instance(): void
    {
        $gameVersion = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $this->mockGetPlayableClass(status: 500);
        $this->mockGetPlayableRace();
        $this->applyBlizzardMocks();

        AssignPlayableClassAndRace::run(Character::factory()->make(), $gameVersion, 5, 7);
        AssignPlayableClassAndRace::run(Character::factory()->make(), $gameVersion, 5, 7);

        Saloon::mockClient()->assertSentCount(2, GetPlayableClassRequest::class);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_keeps_the_previous_race_without_logging_when_the_race_has_no_supported_faction(): void
    {
        $gameVersion = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $gameVersion->playableClasses()->attach(PlayableClass::factory()->create(['id' => 5]));
        $previous = PlayableRace::factory()->create(['id' => 3]);
        $character = Character::factory()->make(['playable_race_id' => $previous->id]);
        $this->mockGetPlayableRace(responseData: ['faction' => ['type' => 'NEUTRAL', 'name' => 'Neutral']]);
        $this->applyBlizzardMocks();
        Log::shouldReceive('warning')->never();

        AssignPlayableClassAndRace::run($character, $gameVersion, 5, 7);

        $this->assertSame(3, $character->playable_race_id);
        $this->assertDatabaseMissing('pivot_game_versions_playable_races', ['playable_race_id' => 7]);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_requests_a_race_with_no_supported_faction_once_per_instance(): void
    {
        $gameVersion = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $gameVersion->playableClasses()->attach(PlayableClass::factory()->create(['id' => 5]));
        $this->mockGetPlayableRace(responseData: ['faction' => ['type' => 'NEUTRAL', 'name' => 'Neutral']]);
        $this->applyBlizzardMocks();

        $action = AssignPlayableClassAndRace::make();
        $action->handle(Character::factory()->make(), $gameVersion, 5, 7);
        $action->handle(Character::factory()->make(), $gameVersion, 5, 7);

        Saloon::mockClient()->assertSentCount(1, GetPlayableRaceRequest::class);
    }
}
