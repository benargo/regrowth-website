<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\FindOrImportPlayableRace;
use App\Contracts\HasShadowAvatars;
use App\Enums\Faction;
use App\Enums\Gender;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceRequest;
use App\Jobs\AttachShadowAvatarToPlayableRace;
use App\Models\PlayableRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([AttachShadowAvatarToPlayableRace::class]);
    }

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

    // ==================== shadow avatars ====================

    #[Group('media')]
    #[Test]
    public function it_queues_both_shadow_avatars_for_an_existing_race_without_them(): void
    {
        PlayableRace::factory()->create(['id' => 7]);

        FindOrImportPlayableRace::run(7, BlizzardNamespace::FOREVER);

        $this->assertQueuedShadowAvatarGenders(7, [Gender::MALE, Gender::FEMALE]);
    }

    #[Group('media')]
    #[Test]
    public function it_queues_both_shadow_avatars_for_an_imported_race(): void
    {
        $this->mockGetPlayableRace();
        $this->applyBlizzardMocks();

        FindOrImportPlayableRace::run(7, BlizzardNamespace::FOREVER);

        $this->assertQueuedShadowAvatarGenders(7, [Gender::MALE, Gender::FEMALE]);
    }

    #[Group('media')]
    #[Test]
    public function it_queues_only_the_missing_shadow_avatar(): void
    {
        Storage::fake('s3');
        $this->raceWithShadowAvatars(7, [Gender::MALE]);

        FindOrImportPlayableRace::run(7, BlizzardNamespace::FOREVER);

        $this->assertQueuedShadowAvatarGenders(7, [Gender::FEMALE]);
    }

    #[Group('media')]
    #[Test]
    public function it_queues_no_shadow_avatars_when_both_are_stored(): void
    {
        Storage::fake('s3');
        $this->raceWithShadowAvatars(7, [Gender::MALE, Gender::FEMALE]);

        FindOrImportPlayableRace::run(7, BlizzardNamespace::FOREVER);

        Queue::assertNotPushed(AttachShadowAvatarToPlayableRace::class);
    }

    #[Group('media')]
    #[Test]
    public function it_does_not_load_the_media_relation_when_both_shadow_avatars_are_stored(): void
    {
        Storage::fake('s3');
        $this->raceWithShadowAvatars(7, [Gender::MALE, Gender::FEMALE]);

        $playableRace = FindOrImportPlayableRace::run(7, BlizzardNamespace::FOREVER);

        $this->assertFalse($playableRace->relationLoaded('media'));
    }

    #[Group('media')]
    #[Test]
    public function it_queues_no_shadow_avatars_for_a_race_it_does_not_import(): void
    {
        $this->mockGetPlayableRace(responseData: ['faction' => ['type' => 'NEUTRAL', 'name' => 'Neutral']]);
        $this->applyBlizzardMocks();

        FindOrImportPlayableRace::run(7, BlizzardNamespace::FOREVER);

        Queue::assertNotPushed(AttachShadowAvatarToPlayableRace::class);
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
     * Create a race with a stored shadow avatar for each of the given genders.
     *
     * @param  list<Gender>  $genders
     */
    private function raceWithShadowAvatars(int $id, array $genders): PlayableRace
    {
        $playableRace = PlayableRace::factory()->create(['id' => $id]);

        foreach ($genders as $gender) {
            $playableRace->addMediaFromString('AVATAR')
                ->usingFileName("{$id}-{$gender->id()}.jpg")
                ->withCustomProperties(['gender' => $gender->id()])
                ->toMediaCollection(HasShadowAvatars::MEDIA_COLLECTION);
        }

        return $playableRace;
    }

    /**
     * Assert exactly one shadow avatar job was queued for the race per given gender, and no others.
     *
     * @param  list<Gender>  $genders
     */
    private function assertQueuedShadowAvatarGenders(int $playableRaceId, array $genders): void
    {
        Queue::assertPushed(AttachShadowAvatarToPlayableRace::class, count($genders));

        foreach ($genders as $gender) {
            Queue::assertPushed(
                AttachShadowAvatarToPlayableRace::class,
                fn (AttachShadowAvatarToPlayableRace $job): bool => $job->playableRaceId === $playableRaceId && $job->gender === $gender,
            );
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
