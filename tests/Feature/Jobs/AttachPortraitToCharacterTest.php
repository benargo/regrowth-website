<?php

namespace Tests\Feature\Jobs;

use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchCharacterPortraitRequest;
use App\Jobs\AttachPortraitToCharacter;
use App\Models\Character;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class AttachPortraitToCharacterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ==================== Job Contract ====================

    #[Group('job-contract')]
    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new AttachPortraitToCharacter(1, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg'));
    }

    #[Group('job-contract')]
    #[Test]
    public function it_has_three_total_attempts(): void
    {
        $job = new AttachPortraitToCharacter(1, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        $this->assertSame(3, $job->tries);
    }

    #[Group('job-contract')]
    #[Test]
    public function it_has_five_minute_backoff_between_attempts(): void
    {
        $job = new AttachPortraitToCharacter(1, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        $this->assertSame([300, 300], $job->backoff());
    }

    #[Group('job-contract')]
    #[Test]
    public function it_has_the_correct_tags(): void
    {
        $job = new AttachPortraitToCharacter(42, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        $this->assertSame(['blizzard', 'character:42'], $job->tags());
    }

    // ==================== Middleware ====================

    #[Group('middleware')]
    #[Test]
    public function it_applies_without_overlapping_middleware(): void
    {
        $job = new AttachPortraitToCharacter(1, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    #[Group('middleware')]
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

    #[Group('middleware')]
    #[Test]
    public function it_releases_the_overlapping_job_after_sixty_seconds(): void
    {
        $job = new AttachPortraitToCharacter(1, 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        /** @var WithoutOverlapping $middleware */
        $middleware = $job->middleware()[0];

        $this->assertSame(60, $middleware->releaseAfter);
    }

    // ==================== Handle ====================

    #[Group('handle')]
    #[Test]
    public function it_fetches_the_portrait_and_attaches_media_to_the_character(): void
    {
        $character = Character::factory()->create();

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class));

        $this->assertTrue($character->fresh()->hasMedia(HasCharacterMedia::MEDIA_COLLECTION));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION);
        $this->assertSame('51042439-avatar.jpg', $media->file_name);
        $this->assertSame(HasCharacterMedia::DEFAULT_MEDIA_SIZE, $media->getCustomProperty('size'));
    }

    #[Group('handle')]
    #[Test]
    public function it_is_idempotent_and_skips_attachment_when_portrait_already_present(): void
    {
        $character = Character::factory()->create();

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';
        $job = new AttachPortraitToCharacter($character->id, $assetUrl);

        $job->handle(app(RenderConnector::class));
        $job->handle(app(RenderConnector::class));

        $this->assertSame(1, $character->fresh()->getMedia(HasCharacterMedia::MEDIA_COLLECTION)->count());
        Saloon::assertSentCount(1);
    }

    #[Group('handle')]
    #[Test]
    public function it_throws_when_the_asset_fetch_returns_a_non_200(): void
    {
        $character = Character::factory()->create();

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: ['code' => 403], status: 403),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg';

        $this->expectException(RequestException::class);

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class));
    }

    // ==================== Uri Input ====================

    #[Group('uri-input')]
    #[Test]
    public function it_accepts_a_uri_instance_for_the_asset_url(): void
    {
        $character = Character::factory()->create();

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = Uri::of('https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION);
        $this->assertSame('51042439-avatar.jpg', $media->file_name);
    }

    #[Group('uri-input')]
    #[Test]
    public function it_round_trips_through_queue_serialization_with_a_uri_asset_url(): void
    {
        $character = Character::factory()->create();

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = Uri::of('https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg');

        $job = new AttachPortraitToCharacter($character->id, $assetUrl);

        /** @var AttachPortraitToCharacter $restored */
        $restored = unserialize(serialize($job));
        $restored->handle(app(RenderConnector::class));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION);
        $this->assertSame('51042439-avatar.jpg', $media->file_name);
    }

    // ==================== Filename ====================

    #[Group('filename')]
    #[Test]
    public function it_derives_the_filename_from_the_url_stripping_query_strings(): void
    {
        $character = Character::factory()->create();

        Saloon::fake([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = 'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg?version=3';

        (new AttachPortraitToCharacter($character->id, $assetUrl))->handle(app(RenderConnector::class));

        $media = $character->fresh()->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION);
        $this->assertSame('51042439-avatar.jpg', $media->file_name);
    }
}
