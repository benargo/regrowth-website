<?php

namespace Tests\Feature\Jobs;

use App\Contracts\HasCharacterMedia;
use App\Events\Broadcasts\CharacterRenderAttached;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchCharacterMediaRequest;
use App\Jobs\AttachRenderToCharacter;
use App\Models\Character;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class AttachRenderToCharacterTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ==================== job contract ====================

    #[Group('contract')]
    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new AttachRenderToCharacter(1));
    }

    #[Group('contract')]
    #[Test]
    public function it_has_three_total_attempts(): void
    {
        $this->assertSame(3, (new AttachRenderToCharacter(1))->tries);
    }

    #[Group('contract')]
    #[Test]
    public function it_has_five_minute_backoff_between_attempts(): void
    {
        $this->assertSame([300, 300], (new AttachRenderToCharacter(1))->backoff());
    }

    #[Group('contract')]
    #[Test]
    public function it_has_the_correct_tags(): void
    {
        $this->assertSame(['blizzard', 'character:42'], (new AttachRenderToCharacter(42))->tags());
    }

    // ==================== middleware ====================

    #[Group('contract')]
    #[Test]
    public function it_scopes_the_overlap_lock_to_the_character_render(): void
    {
        /** @var WithoutOverlapping $middleware */
        $middleware = (new AttachRenderToCharacter(7))->middleware()[0];

        $this->assertSame('character-render:7', $middleware->key);
        $this->assertSame(60, $middleware->releaseAfter);
    }

    // ==================== handle ====================

    #[Group('happy-path')]
    #[Test]
    public function it_fetches_the_render_and_attaches_it_to_the_render_collection(): void
    {
        $character = Character::factory()->create();
        $this->mockGetCharacterMedia();
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $character = $character->fresh();

        $this->assertTrue($character->hasMedia(HasCharacterMedia::MEDIA_COLLECTION_RENDER));
        $this->assertSame('51042439-main-raw.png', $character->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION_RENDER)->file_name);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_does_not_touch_the_portrait_collection(): void
    {
        $character = Character::factory()->create();
        $character->addMediaFromString('AVATAR')
            ->usingFileName('avatar.jpg')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION);

        $this->mockGetCharacterMedia();
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $this->assertSame('avatar.jpg', $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION)->file_name);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_the_render_attached_event(): void
    {
        Event::fake([CharacterRenderAttached::class]);

        $character = Character::factory()->create();
        $this->mockGetCharacterMedia();
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Event::assertDispatched(CharacterRenderAttached::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_returns_early_when_a_render_already_exists(): void
    {
        $character = Character::factory()->create();
        $character->addMediaFromString('EXISTING')
            ->usingFileName('existing.png')
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $this->mockGetCharacterMedia();
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Saloon::assertNotSent(GetCharacterMediaRequest::class);
        Saloon::assertNotSent(FetchCharacterMediaRequest::class);
        $this->assertSame('existing.png', $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION_RENDER)->file_name);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_never_calls_the_character_profile_endpoint(): void
    {
        $character = Character::factory()->create(['gender' => null]);
        $this->mockGetCharacterMedia();
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Saloon::assertNotSent(GetCharacterProfileRequest::class);
        $this->assertNull($character->fresh()->gender);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_the_opaque_vertical_bounds_as_custom_properties(): void
    {
        $character = Character::factory()->create();
        $this->mockGetCharacterMedia();
        $this->mockFetchCharacterMedia(body: $this->pngWithOpaqueBand(width: 10, height: 100, opaqueFrom: 20, opaqueTo: 79));
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $this->assertEqualsWithDelta(0.20, $media->getCustomProperty('visible_top'), 0.05);
        $this->assertEqualsWithDelta(0.80, $media->getCustomProperty('visible_bottom'), 0.05);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_leaves_the_visible_bounds_unset_when_the_render_is_not_a_decodable_image(): void
    {
        $character = Character::factory()->create();
        $this->mockGetCharacterMedia();
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $this->assertNull($media->getCustomProperty('visible_top'));
        $this->assertNull($media->getCustomProperty('visible_bottom'));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_requests_the_render_url_unresized(): void
    {
        $character = Character::factory()->create();
        $this->mockGetCharacterMedia();
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Saloon::assertSent(fn (FetchCharacterMediaRequest $request) => str_contains($request->resolveEndpoint(), '51042439-main-raw.png'));
    }

    // ==================== failure paths ====================

    #[Group('failure-path')]
    #[Test]
    public function it_fails_when_the_character_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new AttachRenderToCharacter(999_999))->handle(app(RenderConnector::class), app(BlizzardConnector::class));
    }

    #[Group('failure-path')]
    #[Test]
    public function it_throws_when_the_render_cdn_returns_an_error(): void
    {
        $character = Character::factory()->create();
        $this->mockGetCharacterMedia();
        $this->mockFetchCharacterMedia(status: 404);
        $this->applyBlizzardMocks();

        $this->expectException(RequestException::class);

        (new AttachRenderToCharacter($character->id))->handle(app(RenderConnector::class), app(BlizzardConnector::class));
    }

    #[Group('failure-path')]
    #[Test]
    public function it_rejects_a_non_blizzard_render_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $character = Character::factory()->create();
        $this->mockGetCharacterMedia(responseData: [
            'assets' => [
                ['key' => 'avatar', 'value' => 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg'],
                ['key' => 'main-raw', 'value' => 'https://evil.example.com/payload.png'],
            ],
        ]);
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id))->handle(app(RenderConnector::class), app(BlizzardConnector::class));
    }

    #[Group('failure-path')]
    #[Test]
    public function it_silently_no_ops_when_the_character_media_lookup_fails(): void
    {
        Event::fake([CharacterRenderAttached::class]);

        $character = Character::factory()->create();
        Saloon::fake([
            self::TOKEN_MOCK_KEY => MockResponse::make(body: self::TOKEN_MOCK_RESPONSE, status: 200),
            GetCharacterMediaRequest::class => MockResponse::make(status: 503),
        ]);

        (new AttachRenderToCharacter($character->id))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        $this->assertFalse($character->fresh()->hasMedia(HasCharacterMedia::MEDIA_COLLECTION_RENDER));
        Event::assertNotDispatched(CharacterRenderAttached::class);
    }

    #[Group('failure-path')]
    #[Test]
    public function it_silently_no_ops_when_no_main_raw_asset_is_present(): void
    {
        Event::fake([CharacterRenderAttached::class]);

        $character = Character::factory()->create();
        $this->mockGetCharacterMedia(responseData: [
            'assets' => [
                ['key' => 'avatar', 'value' => 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg'],
                ['key' => 'inset', 'value' => 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-inset.jpg'],
            ],
        ]);
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id))->handle(app(RenderConnector::class), app(BlizzardConnector::class));

        Saloon::assertNotSent(FetchCharacterMediaRequest::class);
        $this->assertFalse($character->fresh()->hasMedia(HasCharacterMedia::MEDIA_COLLECTION_RENDER));
        Event::assertNotDispatched(CharacterRenderAttached::class);
    }

    // ==================== helpers ====================

    /**
     * Builds a PNG with transparent rows everywhere except an opaque band
     * between `$opaqueFrom` and `$opaqueTo` (inclusive, 0-indexed).
     */
    private function pngWithOpaqueBand(int $width, int $height, int $opaqueFrom, int $opaqueTo): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, false);

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        $opaque = imagecolorallocatealpha($image, 255, 0, 0, 0);
        imagefilledrectangle($image, 0, $opaqueFrom, $width - 1, $opaqueTo, $opaque);

        ob_start();
        imagepng($image);

        return ob_get_clean();
    }
}
