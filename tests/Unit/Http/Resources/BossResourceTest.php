<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\BossResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\RaidResource;
use App\Models\Boss;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('raiding')]
class BossResourceTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockGetItem();
        $this->applyBlizzardMocks();
    }

    #[Test]
    public function it_returns_id(): void
    {
        $boss = Boss::factory()->create();

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertSame($boss->id, $array['id']);
    }

    #[Test]
    public function it_returns_name(): void
    {
        $boss = Boss::factory()->create(['name' => 'Prince Malchezaar']);

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertSame('Prince Malchezaar', $array['name']);
    }

    // ==================== sort order ====================

    #[Test]
    public function it_returns_sort_order(): void
    {
        $boss = Boss::factory()->order(3)->create();

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertSame(3, $array['sort_order']);
    }

    // ==================== raid relation ====================

    #[Test]
    public function it_includes_raid_resource_when_loaded(): void
    {
        $boss = Boss::factory()->create();
        $boss->load('raid');

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertArrayHasKey('raid', $array);
        $this->assertInstanceOf(RaidResource::class, $array['raid']);
    }

    #[Test]
    public function it_omits_raid_when_not_loaded(): void
    {
        $boss = Boss::factory()->create();

        $array = (new BossResource($boss))->resolve(new Request);

        $this->assertArrayNotHasKey('raid', $array);
    }

    // ==================== items relation ====================

    #[Test]
    public function it_includes_items_when_loaded(): void
    {
        $boss = Boss::factory()->withItems(2)->create();
        $boss->load('items');

        $array = (new BossResource($boss))->resolve(new Request);

        $this->assertArrayHasKey('items', $array);
        $this->assertCount(2, $array['items']);
        $this->assertInstanceOf(ItemResource::class, $array['items'][0]);
    }

    #[Test]
    public function it_excludes_items_when_not_loaded(): void
    {
        $boss = Boss::factory()->withItems(2)->create();

        $array = (new BossResource($boss))->resolve(new Request);

        $this->assertArrayNotHasKey('items', $array);
    }

    // ==================== notes ====================

    #[Test]
    public function it_returns_notes(): void
    {
        $boss = Boss::factory()->create(['notes' => 'Interrupt the cast.']);

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertSame('Interrupt the cast.', $array['notes']);
    }

    #[Test]
    public function it_returns_null_notes_when_not_set(): void
    {
        $boss = Boss::factory()->create(['notes' => null]);

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertNull($array['notes']);
    }

    // ==================== slug ====================

    #[Test]
    public function it_returns_slug(): void
    {
        $boss = Boss::factory()->create(['name' => 'Prince Malchezaar']);

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertSame('prince-malchezaar', $array['slug']);
    }

    // ==================== images ====================

    #[Test]
    public function it_returns_images_array(): void
    {
        $boss = Boss::factory()->create();

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertArrayHasKey('images', $array);
        $this->assertIsArray($array['images']);
        $this->assertEmpty($array['images']);
    }

    // ==================== comments count ====================

    #[Test]
    public function it_returns_comments_count_when_counted(): void
    {
        $boss = Boss::factory()->create();
        $boss->loadCount('comments');

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertArrayHasKey('comments_count', $array);
        $this->assertSame(0, $array['comments_count']);
    }

    #[Test]
    public function it_excludes_comments_count_when_not_counted(): void
    {
        $boss = Boss::factory()->create();

        $array = (new BossResource($boss))->resolve(new Request);

        $this->assertArrayNotHasKey('comments_count', $array);
    }

    // ==================== full resource shape ====================

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $boss = Boss::factory()->create();

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('notes', $array);
        $this->assertArrayHasKey('images', $array);
        $this->assertArrayHasKey('sort_order', $array);
    }

    // ==================== management ====================

    #[Test]
    public function the_default_shape_is_unchanged(): void
    {
        $boss = Boss::factory()->create();

        $this->assertSame(
            ['id', 'name', 'slug', 'notes', 'images', 'sort_order'],
            array_keys((new BossResource($boss))->resolve(new Request)),
        );
    }

    #[Group('contract')]
    #[Test]
    public function the_default_shape_leaves_out_the_management_fields_even_when_counted(): void
    {
        $boss = Boss::withUsageCounts()
            ->withCount(['media', 'pastAssignments', 'pastEvents', 'templateEvents'])
            ->find(Boss::factory()->create()->id);

        $array = (new BossResource($boss))->withLinks(['edit' => '/edit'])->resolve(new Request);

        $this->assertSame(['id', 'name', 'slug', 'notes', 'images', 'sort_order'], array_keys($array));
    }

    #[Test]
    public function the_management_scope_adds_links_usage_and_past_link_counts_and_the_strategy_flag(): void
    {
        $boss = Boss::factory()->create(['notes' => null]);
        $counted = Boss::withUsageCounts()
            ->withCount(['media', 'pastAssignments', 'pastEvents', 'templateEvents'])
            ->find($boss->id);

        $array = (new BossResource($counted))
            ->withLinks(['edit' => '/edit', 'destroy' => '/destroy'])
            ->scope('management')
            ->resolve(new Request);

        $this->assertSame([
            'id', 'name', 'slug', 'notes', 'sort_order', 'items_count', 'links', 'meta',
            'past_assignments_count', 'past_events_count', 'template_events_count',
            'upcoming_assignment_groups_count', 'upcoming_assignments_count', 'upcoming_events_count',
        ], array_keys($array));
        $this->assertSame(['edit' => '/edit', 'destroy' => '/destroy'], $array['links']);
        $this->assertSame(['has_strategy' => false], $array['meta']);
        $this->assertSame(0, $array['items_count']);
    }

    #[Test]
    public function the_management_scope_keeps_only_the_raids_header_fields(): void
    {
        $boss = Boss::factory()->create()->load('raid.media');

        $array = (new BossResource($boss))->scope('management')->resolve(new Request);

        $this->assertSame(['id', 'name', 'color', 'banner_url', 'banner_position'], array_keys($array['raid']));
        $this->assertSame($boss->raid_id, $array['raid']['id']);
    }

    #[Test]
    public function the_management_scope_flags_a_strategy_from_notes_or_images(): void
    {
        Storage::fake('s3');
        $withNotes = Boss::factory()->create(['notes' => 'Interrupt the cast.']);
        $withImage = Boss::factory()->create(['notes' => null]);
        $withImage->addMedia(UploadedFile::fake()->image('room.png'))->toMediaCollection();

        $flags = Boss::withCount('media')
            ->whereKey([$withNotes->id, $withImage->id])
            ->get()
            ->map(fn (Boss $boss): bool => (new BossResource($boss))->scope('management')->resolve(new Request)['meta']['has_strategy'])
            ->all();

        $this->assertSame([true, true], $flags);
    }

    #[Test]
    #[TestWith([null])]
    #[TestWith(['management'])]
    public function it_lists_images_by_id_and_url_in_their_order(?string $scope): void
    {
        Storage::fake('s3');
        $boss = Boss::factory()->create();
        $first = $boss->addMedia(UploadedFile::fake()->image('first.png'))->toMediaCollection();
        $second = $boss->addMedia(UploadedFile::fake()->image('second.png'))->toMediaCollection();
        Media::setNewOrder([$second->id, $first->id]);

        $resource = new BossResource($boss->fresh()->load('media'));

        if ($scope !== null) {
            $resource->scope($scope);
        }

        $this->assertSame([
            ['id' => $second->id, 'url' => $second->getUrl()],
            ['id' => $first->id, 'url' => $first->getUrl()],
        ], $resource->resolve(new Request)['images']);
    }

    // ==================== navigation scope ====================

    #[Test]
    public function the_navigation_scope_exposes_only_the_navigation_fields(): void
    {
        $boss = Boss::factory()->create();

        $array = (new BossResource($boss->loadCount(['comments', 'media'])))->scope('navigation')->resolve(new Request);

        $this->assertSame(['id', 'name', 'slug', 'sort_order', 'comments_count', 'meta'], array_keys($array));
    }

    #[Test]
    #[TestWith(['Tank Moroes by the stairs.', false, true])]
    #[TestWith([null, true, true])]
    #[TestWith([null, false, false])]
    #[TestWith(['', false, false])]
    public function the_navigation_scope_flags_a_strategy(?string $notes, bool $hasImage, bool $expected): void
    {
        Storage::fake('s3');
        $boss = Boss::factory()->create(['notes' => $notes]);

        if ($hasImage) {
            $boss->addMedia(UploadedFile::fake()->image('strategy.png'))->toMediaCollection();
        }

        $array = (new BossResource($boss->refresh()->loadCount('media')))->scope('navigation')->resolve(new Request);

        $this->assertSame($expected, $array['meta']['has_strategy']);
    }

    #[Test]
    public function the_navigation_scope_does_not_read_the_media_relation(): void
    {
        $boss = Boss::query()->withCount('media')->find(Boss::factory()->create()->id);

        Model::preventLazyLoading();

        try {
            $array = (new BossResource($boss))->scope('navigation')->resolve(new Request);
        } finally {
            Model::preventLazyLoading(false);
        }

        $this->assertFalse($boss->relationLoaded('media'));
        $this->assertArrayNotHasKey('images', $array);
    }

    #[Test]
    public function the_unscoped_resource_has_no_navigation_meta(): void
    {
        $boss = Boss::factory()->create();

        $array = (new BossResource($boss->loadCount('media')))->resolve(new Request);

        $this->assertArrayNotHasKey('meta', $array);
    }
}
