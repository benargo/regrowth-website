<?php

namespace Tests\Feature\Actions\Characters;

use App\Actions\Characters\RefreshCharacterPortrait;
use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use App\Jobs\AttachPortraitToCharacter;
use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class RefreshCharacterPortraitTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        Bus::fake([AttachPortraitToCharacter::class]);
        Storage::fake('s3');

        $this->gameVersion = GameVersion::factory()->create(['realm' => 'Thunderstrike', 'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY]);
    }

    // ==================== portrait refresh ====================

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_the_portrait_job_when_the_character_has_no_portrait(): void
    {
        $character = Character::factory()->for($this->gameVersion)->create();
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        RefreshCharacterPortrait::run($character);

        Bus::assertDispatched(AttachPortraitToCharacter::class, fn (AttachPortraitToCharacter $job) => $job->characterId === $character->id
            && (string) $job->assetUrl === 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');
    }

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_the_portrait_job_when_the_portrait_is_stale(): void
    {
        $character = $this->characterWithPortrait();
        $this->travel(RefreshCharacterPortrait::STALE_AFTER_SECONDS)->seconds();
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        RefreshCharacterPortrait::run($character);

        Bus::assertDispatched(AttachPortraitToCharacter::class);
    }

    #[Test]
    public function it_does_not_refresh_a_fresh_portrait(): void
    {
        $character = $this->characterWithPortrait();
        $this->travel(RefreshCharacterPortrait::STALE_AFTER_SECONDS - 1)->seconds();
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        RefreshCharacterPortrait::run($character);

        Saloon::assertNotSent(GetCharacterMediaRequest::class);
        Bus::assertNotDispatched(AttachPortraitToCharacter::class);
    }

    // ==================== lookup targeting ====================

    #[Test]
    public function it_looks_up_the_portrait_on_the_characters_game_version_realm_and_namespace(): void
    {
        $gameVersion = GameVersion::factory()->create(['realm' => 'Living Flame', 'blizzard_namespace' => BlizzardNamespace::ERA]);
        $character = Character::factory()->for($gameVersion)->create();
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        RefreshCharacterPortrait::run($character);

        Saloon::assertSent(fn ($request, $response) => $request instanceof GetCharacterMediaRequest
            && str_contains($request->resolveEndpoint(), '/living-flame/')
            && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === BlizzardNamespace::ERA->forProfileRequests(Region::from(config('services.blizzard.region'))));
    }

    #[Test]
    public function it_looks_up_the_portrait_without_a_realm_on_a_forever_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create(['realm' => null, 'blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $character = Character::factory()->for($gameVersion)->create(['name' => 'Thrall Stormrage']);
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        RefreshCharacterPortrait::run($character);

        Saloon::assertSent(fn ($request) => $request instanceof GetCharacterMediaRequest
            && $request->resolveEndpoint() === '/profile/wow/character/thrall-stormrage/character-media');
    }

    #[Test]
    public function it_requests_a_character_with_a_surname_by_its_full_name(): void
    {
        $gameVersion = GameVersion::factory()->withSurnames()->create(['realm' => null, 'blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $character = Character::factory()->for($gameVersion)->withSurname('Stormrage')->create(['name' => 'Thrall']);
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        RefreshCharacterPortrait::run($character);

        Saloon::assertSent(fn ($request) => $request instanceof GetCharacterMediaRequest
            && str_contains($request->resolveEndpoint(), '/thrall-stormrage/'));
    }

    #[Group('edge-case')]
    #[Test]
    public function it_sends_no_request_when_the_namespace_needs_a_realm_but_the_game_version_has_none(): void
    {
        $gameVersion = GameVersion::factory()->create(['realm' => null, 'blizzard_namespace' => BlizzardNamespace::ERA]);
        $character = Character::factory()->for($gameVersion)->create();
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        RefreshCharacterPortrait::run($character);

        Saloon::assertNotSent(GetCharacterMediaRequest::class);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_sends_no_request_when_the_character_has_no_realm(): void
    {
        $character = Character::factory()->create(['game_version_id' => null]);
        $this->mockGetCharacterMedia();
        $this->applyBlizzardMocks();

        RefreshCharacterPortrait::run($character);

        Saloon::assertNotSent(GetCharacterMediaRequest::class);
        Bus::assertNotDispatched(AttachPortraitToCharacter::class);
    }

    // ==================== error handling ====================

    #[Group('error-handling')]
    #[Test]
    public function it_swallows_a_blizzard_error_without_dispatching(): void
    {
        $character = Character::factory()->for($this->gameVersion)->create();
        $this->mockGetCharacterMedia(['type' => 'BLZWEBAPI00000404'], 404);
        $this->applyBlizzardMocks();

        RefreshCharacterPortrait::run($character);

        Bus::assertNotDispatched(AttachPortraitToCharacter::class);
    }

    // ==================== queue if stale ====================

    #[Test]
    public function queue_if_stale_queues_a_refresh_for_a_stale_portrait(): void
    {
        $character = $this->characterWithPortrait();
        $this->travel(RefreshCharacterPortrait::STALE_AFTER_SECONDS)->seconds();
        Queue::fake();

        RefreshCharacterPortrait::queueIfStale($character);

        RefreshCharacterPortrait::assertPushed(1);
    }

    #[Test]
    public function queue_if_stale_does_not_queue_a_refresh_for_a_fresh_portrait(): void
    {
        $character = $this->characterWithPortrait();
        Queue::fake();

        RefreshCharacterPortrait::queueIfStale($character);

        RefreshCharacterPortrait::assertNotPushed();
    }

    // ==================== known missing portraits ====================

    #[Group('edge-case')]
    #[Test]
    public function it_remembers_a_character_without_a_blizzard_avatar(): void
    {
        $character = Character::factory()->for($this->gameVersion)->create();
        $this->mockGetCharacterMedia(['assets' => [
            ['key' => 'inset', 'value' => 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-inset.jpg'],
        ]]);
        $this->applyBlizzardMocks();

        RefreshCharacterPortrait::run($character);

        Bus::assertNotDispatched(AttachPortraitToCharacter::class);
        $this->assertTrue($character->isPortraitKnownMissing());
    }

    #[Group('edge-case')]
    #[Test]
    public function it_remembers_a_character_blizzard_cannot_find(): void
    {
        $character = Character::factory()->for($this->gameVersion)->create();
        $this->mockGetCharacterMedia(['type' => 'BLZWEBAPI00000404'], 404);
        $this->applyBlizzardMocks();

        RefreshCharacterPortrait::run($character);

        $this->assertTrue($character->isPortraitKnownMissing());
    }

    #[Group('error-handling')]
    #[Test]
    public function it_does_not_remember_a_blizzard_outage(): void
    {
        $character = Character::factory()->for($this->gameVersion)->create();
        $this->mockGetCharacterMedia(['type' => 'BLZWEBAPI00000503'], 503);
        $this->applyBlizzardMocks();

        RefreshCharacterPortrait::run($character);

        $this->assertFalse($character->isPortraitKnownMissing());

        Saloon::fake([
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
            GetCharacterMediaRequest::class => MockResponse::make()->throw(fn ($pendingRequest) => new FatalRequestException(new \RuntimeException('Connection refused'), $pendingRequest)),
        ]);

        RefreshCharacterPortrait::run($character);

        $this->assertFalse($character->isPortraitKnownMissing());
    }

    #[Test]
    public function it_does_not_queue_a_refresh_while_the_portrait_is_known_missing(): void
    {
        $character = Character::factory()->for($this->gameVersion)->create();
        $character->rememberPortraitMissing();
        Queue::fake();

        RefreshCharacterPortrait::queueIfStale($character);

        RefreshCharacterPortrait::assertNotPushed();
    }

    private function characterWithPortrait(): Character
    {
        $character = Character::factory()->for($this->gameVersion)->create();
        $character->addMediaFromString('BINARY')
            ->usingFileName('portrait.jpg')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION);

        return $character;
    }
}
