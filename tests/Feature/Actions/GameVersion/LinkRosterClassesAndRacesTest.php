<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\LinkRosterClassesAndRaces;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterMemberData;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\GameVersion;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class LinkRosterClassesAndRacesTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('s3');
    }

    /**
     * @param  array<string, mixed>  $character
     */
    private function member(int $id, int $classId = 5, int $raceId = 7, array $character = []): GuildRosterMemberData
    {
        $payload = array_merge([
            'key' => ['href' => "https://example.test/character/{$id}"],
            'name' => "Member{$id}",
            'id' => $id,
            'level' => 5,
            'playable_class' => ['key' => ['href' => 'https://example.test/class'], 'name' => 'Priest', 'id' => $classId],
            'playable_race' => ['key' => ['href' => 'https://example.test/race'], 'name' => 'Gnome', 'id' => $raceId],
        ], $character);

        return GuildRosterMemberData::from(['character' => $payload, 'rank' => 0]);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_links_an_existing_class_and_race_to_the_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        PlayableClass::factory()->create(['id' => 5]);
        PlayableRace::factory()->create(['id' => 7]);
        $this->mockGetPlayableClass();
        $this->applyBlizzardMocks();

        LinkRosterClassesAndRaces::run($gameVersion, [$this->member(1)]);

        $this->assertTrue($gameVersion->playableClasses()->whereKey(5)->exists());
        $this->assertTrue($gameVersion->playableRaces()->whereKey(7)->exists());
        Saloon::assertNotSent(GetPlayableClassRequest::class);
        Saloon::assertNotSent(GetPlayableRaceRequest::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_imports_a_missing_class_and_race_using_the_game_versions_namespace(): void
    {
        $gameVersion = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia();
        $this->mockGetPlayableRace();
        $this->applyBlizzardMocks();

        LinkRosterClassesAndRaces::run($gameVersion, [$this->member(1)]);

        $this->assertDatabaseHas('playable_classes', ['id' => 5]);
        $this->assertDatabaseHas('playable_races', ['id' => 7]);
        $this->assertTrue($gameVersion->playableClasses()->whereKey(5)->exists());
        $this->assertTrue($gameVersion->playableRaces()->whereKey(7)->exists());
        Saloon::assertSent(
            fn ($request, $response) => $request instanceof GetPlayableClassRequest
                && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === 'static-forever-eu',
        );
        Saloon::assertSent(
            fn ($request, $response) => $request instanceof GetPlayableRaceRequest
                && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === 'static-forever-eu',
        );
    }

    #[Group('edge-case')]
    #[Test]
    public function it_requests_each_distinct_id_only_once(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia();
        $this->mockGetPlayableRace();
        $this->applyBlizzardMocks();

        LinkRosterClassesAndRaces::run($gameVersion, [$this->member(1), $this->member(2), $this->member(3)]);

        Saloon::mockClient()->assertSentCount(1, GetPlayableClassRequest::class);
        Saloon::mockClient()->assertSentCount(1, GetPlayableRaceRequest::class);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_keeps_other_game_version_links(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $otherVersion = GameVersion::factory()->create();
        $existingClass = PlayableClass::factory()->create(['id' => 5]);
        $existingRace = PlayableRace::factory()->create(['id' => 7]);
        $otherVersion->playableClasses()->attach($existingClass->id);
        $otherVersion->playableRaces()->attach($existingRace->id);
        $this->applyBlizzardMocks();

        LinkRosterClassesAndRaces::run($gameVersion, [$this->member(1)]);

        $this->assertTrue($otherVersion->playableClasses()->whereKey(5)->exists());
        $this->assertTrue($otherVersion->playableRaces()->whereKey(7)->exists());
        $this->assertTrue($gameVersion->playableClasses()->whereKey(5)->exists());
    }

    #[Group('edge-case')]
    #[Test]
    public function it_skips_a_member_whose_ids_are_absent_from_the_payload(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->applyBlizzardMocks();

        $member = $this->member(1, character: [
            'playable_class' => ['key' => ['href' => 'https://example.test/class']],
            'playable_race' => ['key' => ['href' => 'https://example.test/race']],
        ]);

        LinkRosterClassesAndRaces::run($gameVersion, [$member]);

        $this->assertSame(0, $gameVersion->playableClasses()->count());
        $this->assertSame(0, $gameVersion->playableRaces()->count());
        Saloon::assertNothingSent();
    }

    #[Group('edge-case')]
    #[Test]
    public function it_logs_a_failed_class_import_and_still_links_the_race(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->mockGetPlayableClass(status: 500);
        $this->mockGetPlayableRace();
        $this->applyBlizzardMocks();

        Log::shouldReceive('warning')->once()->withArgs(
            fn ($message, $context) => $message === 'Failed to import playable class from Blizzard.'
                && $context['game_version_id'] === $gameVersion->id
                && $context['playable_class_id'] === 5
                && isset($context['error']),
        );

        LinkRosterClassesAndRaces::run($gameVersion, [$this->member(1)]);

        $this->assertSame(0, $gameVersion->playableClasses()->count());
        $this->assertTrue($gameVersion->playableRaces()->whereKey(7)->exists());
        Queue::assertNotPushed(AttachBlizzardIconToModel::class);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_does_not_link_a_race_without_a_supported_faction(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia();
        $this->mockGetPlayableRace(responseData: ['faction' => ['type' => 'NEUTRAL', 'name' => 'Neutral']]);
        $this->applyBlizzardMocks();

        Log::shouldReceive('warning')->never();

        LinkRosterClassesAndRaces::run($gameVersion, [$this->member(1)]);

        $this->assertDatabaseMissing('pivot_game_versions_playable_races', ['playable_race_id' => 7]);
        $this->assertDatabaseHas('pivot_game_versions_playable_classes', ['game_version_id' => $gameVersion->id, 'playable_class_id' => 5]);
    }
}
