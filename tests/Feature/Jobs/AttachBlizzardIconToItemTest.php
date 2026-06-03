<?php

namespace Tests\Feature\Jobs;

use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Jobs\AttachBlizzardIconToItem;
use App\Models\Item;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class AttachBlizzardIconToItemTest extends TestCase
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
        $this->assertInstanceOf(ShouldQueue::class, new AttachBlizzardIconToItem(1, 'https://example.test/icon.jpg'));
    }

    #[Group('job-contract')]
    #[Test]
    public function it_has_three_total_attempts(): void
    {
        $job = new AttachBlizzardIconToItem(1, 'https://example.test/icon.jpg');

        $this->assertSame(3, $job->tries);
    }

    #[Group('job-contract')]
    #[Test]
    public function it_has_five_minute_backoff_between_attempts(): void
    {
        $job = new AttachBlizzardIconToItem(1, 'https://example.test/icon.jpg');

        $this->assertSame([300, 300], $job->backoff());
    }

    #[Group('job-contract')]
    #[Test]
    public function it_has_the_correct_tags(): void
    {
        $job = new AttachBlizzardIconToItem(42, 'https://example.test/icon.jpg');

        $this->assertSame(['blizzard', 'item:42'], $job->tags());
    }

    // ==================== Handle ====================

    #[Group('handle')]
    #[Test]
    public function it_fetches_the_asset_and_attaches_media_to_the_item(): void
    {
        $item = Item::factory()->create();

        Saloon::fake([
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = "https://render.worldofwarcraft.com/eu/icons/56/item_{$item->id}.jpg";

        (new AttachBlizzardIconToItem($item->id, $assetUrl))->handle(app(RenderConnector::class));

        $this->assertTrue($item->fresh()->hasMedia('blizzard_icons'));

        $media = $item->fresh()->getFirstMedia('blizzard_icons');
        $this->assertSame("item_{$item->id}.jpg", $media->file_name);
        $this->assertSame(56, $media->getCustomProperty('size'));
    }

    #[Group('handle')]
    #[Test]
    public function it_is_idempotent_and_skips_attachment_when_icon_already_present(): void
    {
        $item = Item::factory()->create();

        Saloon::fake([
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = "https://render.worldofwarcraft.com/eu/icons/56/item_{$item->id}.jpg";
        $job = new AttachBlizzardIconToItem($item->id, $assetUrl);

        $job->handle(app(RenderConnector::class));
        $job->handle(app(RenderConnector::class));

        $this->assertSame(1, $item->fresh()->getMedia('blizzard_icons')->count());
        Saloon::assertSentCount(1);
    }

    #[Group('handle')]
    #[Test]
    public function it_throws_when_the_asset_fetch_returns_a_non_200(): void
    {
        $item = Item::factory()->create();

        Saloon::fake([
            FetchAssetRequest::class => MockResponse::make(body: ['code' => 403], status: 403),
        ]);

        $assetUrl = "https://render.worldofwarcraft.com/eu/icons/56/item_{$item->id}.jpg";

        $this->expectException(RequestException::class);

        (new AttachBlizzardIconToItem($item->id, $assetUrl))->handle(app(RenderConnector::class));
    }

    // ==================== Retail Asset URL ====================

    #[Group('retail-asset-url')]
    #[Test]
    public function retail_asset_url_strips_classicann_prefix_from_region(): void
    {
        $cases = [
            'https://render.worldofwarcraft.com/classicann-eu/icons/56/inv_jewelry_ring_57.jpg' => 'https://render.worldofwarcraft.com/eu/icons/56/inv_jewelry_ring_57.jpg',
            'https://render.worldofwarcraft.com/classicann-us/icons/56/spell_fire_fireball.jpg' => 'https://render.worldofwarcraft.com/us/icons/56/spell_fire_fireball.jpg',
            'https://render.worldofwarcraft.com/classicann-kr/icons/56/ability_warrior_charge.jpg' => 'https://render.worldofwarcraft.com/kr/icons/56/ability_warrior_charge.jpg',
        ];

        foreach ($cases as $input => $expected) {
            $job = new AttachBlizzardIconToItem(1, $input);
            $this->assertSame($expected, $job->retailAssetUrl(), "Failed for: {$input}");
        }
    }

    #[Group('retail-asset-url')]
    #[Test]
    public function retail_asset_url_is_unchanged_when_url_already_uses_retail_region(): void
    {
        $job = new AttachBlizzardIconToItem(1, 'https://render.worldofwarcraft.com/eu/icons/56/inv_misc_questionmark.jpg');

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/icons/56/inv_misc_questionmark.jpg',
            $job->retailAssetUrl(),
        );
    }

    #[Group('retail-asset-url')]
    #[Test]
    public function it_fetches_and_attaches_using_the_retail_url(): void
    {
        $item = Item::factory()->create();

        Saloon::fake([
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $assetUrl = "https://render.worldofwarcraft.com/classicann-eu/icons/56/item_{$item->id}.jpg";

        (new AttachBlizzardIconToItem($item->id, $assetUrl))->handle(app(RenderConnector::class));

        $this->assertTrue($item->fresh()->hasMedia('blizzard_icons'));

        $media = $item->fresh()->getFirstMedia('blizzard_icons');
        $this->assertSame("item_{$item->id}.jpg", $media->file_name);

        Saloon::assertSent(function (FetchAssetRequest $request) use ($item): bool {
            return str_contains($request->resolveEndpoint(), "/eu/icons/56/item_{$item->id}.jpg");
        });
    }
}
