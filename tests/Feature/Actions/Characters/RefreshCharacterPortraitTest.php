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
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
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

        Bus::fake([AttachPortraitToCharacter::class]);
        Storage::fake('public');

        $this->gameVersion = GameVersion::factory()->create(['realm' => 'Thunderstrike', 'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY]);
    }

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

    private function characterWithPortrait(): Character
    {
        $character = Character::factory()->for($this->gameVersion)->create();
        $character->addMediaFromString('BINARY')
            ->usingFileName('portrait.jpg')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION);

        return $character;
    }
}
