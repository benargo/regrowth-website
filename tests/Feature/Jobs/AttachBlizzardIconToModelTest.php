<?php

namespace Tests\Feature\Jobs;

use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\Item;
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

#[Group('blizzard-integration')]
#[Group('media')]
class AttachBlizzardIconToModelTest extends TestCase
{
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
        $this->assertInstanceOf(ShouldQueue::class, new AttachBlizzardIconToModel(Item::class, 1, 'https://example.test/icon.jpg'));
    }

    #[Group('contract')]
    #[Test]
    public function it_has_three_total_attempts(): void
    {
        $job = new AttachBlizzardIconToModel(Item::class, 1, 'https://example.test/icon.jpg');

        $this->assertSame(3, $job->tries);
    }

    #[Group('contract')]
    #[Test]
    public function it_has_five_minute_backoff_between_attempts(): void
    {
        $job = new AttachBlizzardIconToModel(Item::class, 1, 'https://example.test/icon.jpg');

        $this->assertSame([300, 300], $job->backoff());
    }

    #[Group('contract')]
    #[Test]
    public function it_has_the_correct_tags(): void
    {
        $job = new AttachBlizzardIconToModel(Item::class, 42, 'https://example.test/icon.jpg');

        $this->assertSame(['blizzard', 'model:Item:42'], $job->tags());
    }

    #[Group('error-handling')]
    #[Test]
    public function it_throws_when_model_class_does_not_implement_has_blizzard_icons(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AttachBlizzardIconToModel(\stdClass::class, 1, 'https://example.test/icon.jpg');
    }

    // ==================== Middleware ====================

    #[Group('contract')]
    #[Test]
    public function it_applies_without_overlapping_middleware(): void
    {
        $job = new AttachBlizzardIconToModel(Item::class, 42, 'https://example.test/icon.jpg');
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    #[Group('contract')]
    #[Test]
    public function it_scopes_the_overlap_lock_to_the_icon_name(): void
    {
        $jobA = new AttachBlizzardIconToModel(Item::class, 1, 'https://example.test/icon_a.jpg');
        $jobB = new AttachBlizzardIconToModel(Item::class, 2, 'https://example.test/icon_b.jpg');
        $jobC = new AttachBlizzardIconToModel(Item::class, 3, 'https://example.test/icon_a.jpg');

        /** @var WithoutOverlapping $middlewareA */
        $middlewareA = $jobA->middleware()[0];
        /** @var WithoutOverlapping $middlewareB */
        $middlewareB = $jobB->middleware()[0];
        /** @var WithoutOverlapping $middlewareC */
        $middlewareC = $jobC->middleware()[0];

        $this->assertSame('blizzard-icon:icon_a.jpg', $middlewareA->key);
        $this->assertSame('blizzard-icon:icon_b.jpg', $middlewareB->key);
        $this->assertSame($middlewareA->key, $middlewareC->key);
        $this->assertNotSame($middlewareA->key, $middlewareB->key);
    }

    #[Group('contract')]
    #[Test]
    public function it_releases_the_overlapping_job_after_sixty_seconds(): void
    {
        $job = new AttachBlizzardIconToModel(Item::class, 1, 'https://example.test/icon.jpg');

        /** @var WithoutOverlapping $middleware */
        $middleware = $job->middleware()[0];

        $this->assertSame(60, $middleware->releaseAfter);
    }

    // ==================== Handle ====================

    #[Group('happy-path')]
    #[Test]
    public function it_fetches_the_asset_and_attaches_media_to_the_model(): void
    {
        $item = Item::factory()->create();

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = "https://render.worldofwarcraft.com/eu/icons/56/item_{$item->id}.jpg";

        (new AttachBlizzardIconToModel(Item::class, $item->id, $assetUrl))->handle(app(RenderConnector::class));

        $this->assertTrue($item->fresh()->hasMedia('blizzard_icons'));

        $media = $item->fresh()->getFirstMedia('blizzard_icons');
        $this->assertSame("item_{$item->id}.jpg", $media->file_name);
        $this->assertSame(56, $media->getCustomProperty('size'));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_is_idempotent_and_skips_attachment_when_icon_already_present(): void
    {
        $item = Item::factory()->create();

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = "https://render.worldofwarcraft.com/eu/icons/56/item_{$item->id}.jpg";
        $job = new AttachBlizzardIconToModel(Item::class, $item->id, $assetUrl);

        $job->handle(app(RenderConnector::class));
        $job->handle(app(RenderConnector::class));

        $this->assertSame(1, $item->fresh()->getMedia('blizzard_icons')->count());
        Saloon::assertSentCount(1);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_throws_when_the_asset_fetch_returns_a_non_200(): void
    {
        $item = Item::factory()->create();

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: ['code' => 403], status: 403),
        ]);

        $assetUrl = "https://render.worldofwarcraft.com/eu/icons/56/item_{$item->id}.jpg";

        $this->expectException(RequestException::class);

        (new AttachBlizzardIconToModel(Item::class, $item->id, $assetUrl))->handle(app(RenderConnector::class));
    }

    // ==================== Retail Asset URL ====================

    // ==================== Uri Input ====================

    #[Group('happy-path')]
    #[Test]
    public function it_accepts_a_uri_instance_for_the_asset_url(): void
    {
        $item = Item::factory()->create();

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = Uri::of("https://render.worldofwarcraft.com/eu/icons/56/item_{$item->id}.jpg");

        (new AttachBlizzardIconToModel(Item::class, $item->id, $assetUrl))->handle(app(RenderConnector::class));

        $media = $item->fresh()->getFirstMedia('blizzard_icons');
        $this->assertSame("item_{$item->id}.jpg", $media->file_name);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_round_trips_through_queue_serialization_with_a_uri_asset_url(): void
    {
        $assetUrl = Uri::of('https://render.worldofwarcraft.com/classicann-eu/icons/56/inv_jewelry_ring_57.jpg');

        $job = new AttachBlizzardIconToModel(Item::class, 7, $assetUrl);

        /** @var AttachBlizzardIconToModel $restored */
        $restored = unserialize(serialize($job));

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/icons/56/inv_jewelry_ring_57.jpg',
            (string) $restored->retailAssetUrl(),
        );
    }

    #[Group('happy-path')]
    #[Test]
    public function retail_asset_url_strips_classicann_prefix_from_region(): void
    {
        $cases = [
            'https://render.worldofwarcraft.com/classicann-eu/icons/56/inv_jewelry_ring_57.jpg' => 'https://render.worldofwarcraft.com/eu/icons/56/inv_jewelry_ring_57.jpg',
            'https://render.worldofwarcraft.com/classicann-us/icons/56/spell_fire_fireball.jpg' => 'https://render.worldofwarcraft.com/us/icons/56/spell_fire_fireball.jpg',
            'https://render.worldofwarcraft.com/classicann-kr/icons/56/ability_warrior_charge.jpg' => 'https://render.worldofwarcraft.com/kr/icons/56/ability_warrior_charge.jpg',
        ];

        foreach ($cases as $input => $expected) {
            $job = new AttachBlizzardIconToModel(Item::class, 1, $input);
            $this->assertSame($expected, (string) $job->retailAssetUrl(), "Failed for: {$input}");
        }
    }

    #[Group('happy-path')]
    #[Test]
    public function retail_asset_url_is_unchanged_when_url_already_uses_retail_region(): void
    {
        $job = new AttachBlizzardIconToModel(Item::class, 1, 'https://render.worldofwarcraft.com/eu/icons/56/inv_misc_questionmark.jpg');

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/icons/56/inv_misc_questionmark.jpg',
            (string) $job->retailAssetUrl(),
        );
    }

    #[Group('happy-path')]
    #[Test]
    public function it_fetches_and_attaches_using_the_retail_url(): void
    {
        $item = Item::factory()->create();

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = "https://render.worldofwarcraft.com/classicann-eu/icons/56/item_{$item->id}.jpg";

        (new AttachBlizzardIconToModel(Item::class, $item->id, $assetUrl))->handle(app(RenderConnector::class));

        $this->assertTrue($item->fresh()->hasMedia('blizzard_icons'));

        $media = $item->fresh()->getFirstMedia('blizzard_icons');
        $this->assertSame("item_{$item->id}.jpg", $media->file_name);

        Saloon::assertSent(function (FetchIconRequest $request) use ($item): bool {
            return str_contains($request->resolveEndpoint(), "/eu/icons/56/item_{$item->id}.jpg");
        });
    }
}
