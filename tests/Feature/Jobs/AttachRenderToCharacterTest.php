<?php

namespace Tests\Feature\Jobs;

use App\Contracts\HasCharacterMedia;
use App\Events\Broadcasts\CharacterRenderAttached;
use App\Http\Integrations\Blizzard\RenderConnector;
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
use Illuminate\Support\Uri;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class AttachRenderToCharacterTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    private const RENDER_URL = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-main-raw.png';

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
        $this->assertInstanceOf(ShouldQueue::class, new AttachRenderToCharacter(1, self::RENDER_URL));
    }

    #[Group('contract')]
    #[Test]
    public function it_has_three_total_attempts(): void
    {
        $this->assertSame(3, (new AttachRenderToCharacter(1, self::RENDER_URL))->tries);
    }

    #[Group('contract')]
    #[Test]
    public function it_has_five_minute_backoff_between_attempts(): void
    {
        $this->assertSame([300, 300], (new AttachRenderToCharacter(1, self::RENDER_URL))->backoff());
    }

    #[Group('contract')]
    #[Test]
    public function it_has_the_correct_tags(): void
    {
        $this->assertSame(['blizzard', 'character:42'], (new AttachRenderToCharacter(42, self::RENDER_URL))->tags());
    }

    #[Group('contract')]
    #[Test]
    public function it_accepts_the_asset_url_as_a_string_or_a_uri(): void
    {
        $fromString = new AttachRenderToCharacter(1, self::RENDER_URL);
        $fromUri = new AttachRenderToCharacter(1, Uri::of(self::RENDER_URL));

        $this->assertInstanceOf(Uri::class, $fromString->assetUrl);
        $this->assertSame((string) $fromString->assetUrl, (string) $fromUri->assetUrl);
    }

    // ==================== middleware ====================

    #[Group('contract')]
    #[Test]
    public function it_scopes_the_overlap_lock_to_the_character_render(): void
    {
        /** @var WithoutOverlapping $middleware */
        $middleware = (new AttachRenderToCharacter(7, self::RENDER_URL))->middleware()[0];

        $this->assertSame('character-render:7', $middleware->key);
        $this->assertSame(60, $middleware->releaseAfter);
    }

    // ==================== handle ====================

    #[Group('happy-path')]
    #[Test]
    public function it_fetches_the_render_and_attaches_it_to_the_render_collection(): void
    {
        $character = Character::factory()->create();
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id, self::RENDER_URL))->handle(app(RenderConnector::class));

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

        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id, self::RENDER_URL))->handle(app(RenderConnector::class));

        $this->assertSame('avatar.jpg', $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION)->file_name);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_dispatches_the_render_attached_event(): void
    {
        Event::fake([CharacterRenderAttached::class]);

        $character = Character::factory()->create();
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id, self::RENDER_URL))->handle(app(RenderConnector::class));

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

        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id, self::RENDER_URL))->handle(app(RenderConnector::class));

        Saloon::assertNotSent(FetchCharacterMediaRequest::class);
        $this->assertSame('existing.png', $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION_RENDER)->file_name);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_never_calls_the_character_profile_endpoint(): void
    {
        $character = Character::factory()->create(['gender' => null]);
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id, self::RENDER_URL))->handle(app(RenderConnector::class));

        Saloon::assertNotSent(GetCharacterProfileRequest::class);
        $this->assertNull($character->fresh()->gender);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_the_opaque_vertical_bounds_as_custom_properties(): void
    {
        $character = Character::factory()->create();
        $this->mockFetchCharacterMedia(body: $this->pngWithOpaqueBand(width: 10, height: 100, opaqueFrom: 20, opaqueTo: 79));
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id, self::RENDER_URL))->handle(app(RenderConnector::class));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $this->assertEqualsWithDelta(0.20, $media->getCustomProperty('visible_top'), 0.05);
        $this->assertEqualsWithDelta(0.80, $media->getCustomProperty('visible_bottom'), 0.05);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_leaves_the_visible_bounds_unset_when_the_render_is_not_a_decodable_image(): void
    {
        $character = Character::factory()->create();
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id, self::RENDER_URL))->handle(app(RenderConnector::class));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        $this->assertNull($media->getCustomProperty('visible_top'));
        $this->assertNull($media->getCustomProperty('visible_bottom'));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_requests_the_render_url_unresized(): void
    {
        $character = Character::factory()->create();
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id, self::RENDER_URL))->handle(app(RenderConnector::class));

        Saloon::assertSent(fn (FetchCharacterMediaRequest $request) => str_contains($request->resolveEndpoint(), '51042439-main-raw.png'));
    }

    // ==================== failure paths ====================

    #[Group('failure-path')]
    #[Test]
    public function it_fails_when_the_character_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new AttachRenderToCharacter(999_999, self::RENDER_URL))->handle(app(RenderConnector::class));
    }

    #[Group('failure-path')]
    #[Test]
    public function it_throws_when_the_render_cdn_returns_an_error(): void
    {
        $character = Character::factory()->create();
        $this->mockFetchCharacterMedia(status: 404);
        $this->applyBlizzardMocks();

        $this->expectException(RequestException::class);

        (new AttachRenderToCharacter($character->id, self::RENDER_URL))->handle(app(RenderConnector::class));
    }

    #[Group('failure-path')]
    #[Test]
    public function it_rejects_a_non_blizzard_render_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $character = Character::factory()->create();
        $this->mockFetchCharacterMedia();
        $this->applyBlizzardMocks();

        (new AttachRenderToCharacter($character->id, 'https://evil.example.com/payload.png'))
            ->handle(app(RenderConnector::class));
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
