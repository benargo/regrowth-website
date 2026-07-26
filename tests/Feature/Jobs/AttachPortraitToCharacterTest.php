<?php

namespace Tests\Feature\Jobs;

use App\Contracts\HasCharacterMedia;
use App\Enums\Gender;
use App\Events\Broadcasts\CharacterPortraitAttached;
use App\Events\CharacterUpdated;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchCharacterPortraitRequest;
use App\Jobs\AttachPortraitToCharacter;
use App\Models\Character;
use App\Models\PlayableRace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class AttachPortraitToCharacterTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ==================== Job Contract ====================

    #[Group('contract')]
    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new AttachPortraitToCharacter(1, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg'));
    }

    #[Group('contract')]
    #[Test]
    public function it_has_three_total_attempts(): void
    {
        $job = new AttachPortraitToCharacter(1, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        $this->assertSame(3, $job->tries);
    }

    #[Group('contract')]
    #[Test]
    public function it_has_five_minute_backoff_between_attempts(): void
    {
        $job = new AttachPortraitToCharacter(1, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        $this->assertSame([300, 300], $job->backoff());
    }

    #[Group('contract')]
    #[Test]
    public function it_has_the_correct_tags(): void
    {
        $job = new AttachPortraitToCharacter(42, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        $this->assertSame(['blizzard', 'character:42'], $job->tags());
    }

    // ==================== Middleware ====================

    #[Group('contract')]
    #[Test]
    public function it_applies_without_overlapping_middleware(): void
    {
        $job = new AttachPortraitToCharacter(1, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    #[Group('contract')]
    #[Test]
    public function it_scopes_the_overlap_lock_to_the_character(): void
    {
        $jobA = new AttachPortraitToCharacter(1, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');
        $jobB = new AttachPortraitToCharacter(2, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        /** @var WithoutOverlapping $middlewareA */
        $middlewareA = $jobA->middleware()[0];
        /** @var WithoutOverlapping $middlewareB */
        $middlewareB = $jobB->middleware()[0];

        $this->assertSame('character-portrait:1', $middlewareA->key);
        $this->assertSame('character-portrait:2', $middlewareB->key);
        $this->assertNotSame($middlewareA->key, $middlewareB->key);
    }

    #[Group('contract')]
    #[Test]
    public function it_releases_the_overlapping_job_after_sixty_seconds(): void
    {
        $job = new AttachPortraitToCharacter(1, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        /** @var WithoutOverlapping $middleware */
        $middleware = $job->middleware()[0];

        $this->assertSame(60, $middleware->releaseAfter);
    }

    // ==================== Handle ====================

    #[Group('happy-path')]
    #[Test]
    public function it_fetches_the_portrait_and_attaches_media_to_the_character(): void
    {
        $character = Character::factory()->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(), status: 200),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $this->assertTrue($character->fresh()->hasMedia(HasCharacterMedia::MEDIA_COLLECTION));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION);
        $this->assertSame('51042439-avatar.jpg', $media->file_name);
        $this->assertSame(HasCharacterMedia::DEFAULT_MEDIA_SIZE, $media->getCustomProperty('size'));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_is_idempotent_and_skips_attachment_when_portrait_already_present(): void
    {
        $character = Character::factory()->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(), status: 200),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';
        $job = new AttachPortraitToCharacter($character->id, $assetUrl);

        $job->handle(app(RenderConnector::class), app(BlizzardConnector::class));
        $job->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $this->assertSame(1, $character->fresh()->getMedia(HasCharacterMedia::MEDIA_COLLECTION)->count());
        Saloon::assertSentCount(3);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_throws_when_the_asset_fetch_returns_a_non_200(): void
    {
        $character = Character::factory()->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(), status: 200),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: ['code' => 403], status: 403),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        $this->expectException(RequestException::class);

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class), app(BlizzardConnector::class));
    }

    // ==================== Uri Input ====================

    #[Group('happy-path')]
    #[Test]
    public function it_accepts_a_uri_instance_for_the_asset_url(): void
    {
        $character = Character::factory()->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(), status: 200),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = Uri::of('https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION);
        $this->assertSame('51042439-avatar.jpg', $media->file_name);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_round_trips_through_queue_serialization_with_a_uri_asset_url(): void
    {
        $character = Character::factory()->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(), status: 200),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = Uri::of('https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        $job = new AttachPortraitToCharacter($character->id, $assetUrl);

        /** @var AttachPortraitToCharacter $restored */
        $restored = unserialize(serialize($job));
        $restored->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION);
        $this->assertSame('51042439-avatar.jpg', $media->file_name);
    }

    // ==================== Filename ====================

    #[Group('happy-path')]
    #[Test]
    public function it_derives_the_filename_from_the_url_stripping_query_strings(): void
    {
        $character = Character::factory()->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(), status: 200),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg?version=3';

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION);
        $this->assertSame('51042439-avatar.jpg', $media->file_name);
    }

    // ==================== Gender ====================

    #[Group('happy-path')]
    #[Test]
    public function it_syncs_male_gender_from_the_blizzard_profile_when_gender_is_null(): void
    {
        $character = Character::factory()->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse('Male'), status: 200),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))
            ->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $this->assertSame(Gender::MALE, $character->fresh()->gender);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_syncs_female_gender_from_the_blizzard_profile_when_gender_is_null(): void
    {
        $character = Character::factory()->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse('Female'), status: 200),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))
            ->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $this->assertSame(Gender::FEMALE, $character->fresh()->gender);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_does_not_overwrite_gender_when_already_set(): void
    {
        $character = Character::factory()->create(['gender' => Gender::FEMALE]);

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))
            ->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Saloon::assertNotSent(GetCharacterProfileRequest::class);
        $this->assertSame(Gender::FEMALE, $character->fresh()->gender);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_syncs_gender_even_when_portrait_is_already_attached(): void
    {
        $character = Character::factory()->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse('Male'), status: 200),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        // First run: attaches portrait and syncs gender.
        (new AttachPortraitToCharacter($character->id, $assetUrl))
            ->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        // Simulate re-run scenario: portrait still attached, gender cleared.
        $character->fresh()->updateQuietly(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse('Male'), status: 200),
        ]);

        // Second run: portrait already present (skipped), gender still synced.
        (new AttachPortraitToCharacter($character->id, $assetUrl))
            ->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $this->assertSame(Gender::MALE, $character->fresh()->gender);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_skips_gender_sync_silently_when_the_profile_api_returns_an_error(): void
    {
        $character = Character::factory()->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'], status: 404),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))
            ->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $this->assertNull($character->fresh()->gender);
        $this->assertTrue($character->fresh()->hasMedia(HasCharacterMedia::MEDIA_COLLECTION));
    }

    #[Group('error-handling')]
    #[Test]
    public function it_skips_gender_sync_silently_when_the_profile_returns_an_unrecognised_gender_value(): void
    {
        $character = Character::factory()->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse('Unknown'), status: 200),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))
            ->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $this->assertNull($character->fresh()->gender);
        $this->assertTrue($character->fresh()->hasMedia(HasCharacterMedia::MEDIA_COLLECTION));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_does_not_fire_character_updated_event_when_saving_gender(): void
    {
        Event::fake([CharacterUpdated::class]);

        $character = Character::factory()->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(), status: 200),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))
            ->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Event::assertNotDispatched(CharacterUpdated::class);
    }

    // ==================== Fallback URL ====================

    #[Group('happy-path')]
    #[Test]
    public function it_appends_the_fallback_alt_parameter_to_the_portrait_request(): void
    {
        $race = PlayableRace::factory()->create(['id' => 2]);
        $character = Character::factory()->withPlayableRace($race)->create(['gender' => Gender::FEMALE]);

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Saloon::assertSent(function (FetchCharacterPortraitRequest $request, Response $response): bool {
            return $response->getPendingRequest()->query()->get('alt') === '/shadow/avatar/2-1.jpg';
        });
    }

    #[Group('happy-path')]
    #[Test]
    public function it_uses_male_gender_id_zero_in_the_fallback_parameter(): void
    {
        $race = PlayableRace::factory()->create(['id' => 1]);
        $character = Character::factory()->withPlayableRace($race)->create(['gender' => Gender::MALE]);

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Saloon::assertSent(function (FetchCharacterPortraitRequest $request, Response $response): bool {
            return $response->getPendingRequest()->query()->get('alt') === '/shadow/avatar/1-0.jpg';
        });
    }

    #[Group('happy-path')]
    #[Test]
    public function it_omits_the_fallback_parameter_when_gender_is_null(): void
    {
        $race = PlayableRace::factory()->create(['id' => 2]);
        $character = Character::factory()->withPlayableRace($race)->create(['gender' => null]);

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(body: $this->makeTokenResponse(), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'], status: 404),
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Saloon::assertNotSent(function ($request, Response $response): bool {
            return $request instanceof FetchCharacterPortraitRequest
                && $response->getPendingRequest()->query()->get('alt') !== null;
        });
    }

    #[Group('happy-path')]
    #[Test]
    public function it_omits_the_fallback_parameter_when_race_id_is_null(): void
    {
        $character = Character::factory()->create(['gender' => Gender::MALE]);

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Saloon::assertNotSent(function ($request, Response $response): bool {
            return $request instanceof FetchCharacterPortraitRequest
                && $response->getPendingRequest()->query()->get('alt') !== null;
        });
    }

    #[Group('happy-path')]
    #[Test]
    public function it_still_derives_the_filename_without_the_fallback_query_string(): void
    {
        $race = PlayableRace::factory()->create(['id' => 2]);
        $character = Character::factory()->withPlayableRace($race)->create(['gender' => Gender::FEMALE]);

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION);
        $this->assertSame('51042439-avatar.jpg', $media->file_name);
    }

    // ==================== Broadcast ====================

    #[Group('broadcasting')]
    #[Test]
    public function it_dispatches_character_portrait_attached_after_successfully_attaching_media(): void
    {
        Event::fake([CharacterPortraitAttached::class]);

        $character = Character::factory()->create(['gender' => Gender::MALE]);

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Event::assertDispatched(CharacterPortraitAttached::class, fn ($e) => $e->characterId === $character->id);
    }

    #[Group('broadcasting')]
    #[Test]
    public function it_does_not_dispatch_character_portrait_attached_when_portrait_already_present(): void
    {
        Event::fake([CharacterPortraitAttached::class]);

        $character = Character::factory()->create(['gender' => Gender::MALE]);

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        // First run attaches media.
        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Event::assertDispatched(CharacterPortraitAttached::class);
        Event::fake([CharacterPortraitAttached::class]);

        // Second run hits the idempotent early-return path.
        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Event::assertNotDispatched(CharacterPortraitAttached::class);
    }

    // ==================== Helpers ====================

    /**
     * @return array<string, mixed>
     */
    private function makeProfileResponse(string $gender = 'Male'): array
    {
        return [
            'id' => 1,
            'name' => 'Testcharacter',
            'gender' => ['type' => strtoupper($gender), 'name' => $gender],
            'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            'race' => ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Orc', 'id' => 1],
            'character_class' => ['key' => ['href' => 'https://example.test/class/1'], 'name' => 'Shaman', 'id' => 1],
            'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike'],
            'level' => 70,
            'last_login_timestamp' => 0,
            'average_item_level' => 0,
            'equipped_item_level' => 0,
        ];
    }
}
