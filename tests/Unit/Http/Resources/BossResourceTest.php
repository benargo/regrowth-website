<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\BossResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\RaidResource;
use App\Models\Boss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
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
    public function it_returns_raid_id_when_raid_not_loaded(): void
    {
        $boss = Boss::factory()->create();

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertArrayHasKey('raid', $array);
        $this->assertSame($boss->raid_id, $array['raid']);
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
            ['id', 'name', 'slug', 'notes', 'images', 'sort_order', 'raid'],
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

        $this->assertSame(['id', 'name', 'slug', 'notes', 'images', 'sort_order', 'raid'], array_keys($array));
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
            'id', 'name', 'slug', 'notes', 'sort_order', 'raid', 'items_count', 'links', 'meta',
            'past_assignments_count', 'past_events_count', 'raid_id', 'template_events_count',
            'upcoming_assignment_groups_count', 'upcoming_assignments_count', 'upcoming_events_count',
        ], array_keys($array));
        $this->assertSame(['edit' => '/edit', 'destroy' => '/destroy'], $array['links']);
        $this->assertSame(['has_strategy' => false], $array['meta']);
        $this->assertSame($boss->raid_id, $array['raid_id']);
        $this->assertSame(0, $array['items_count']);
    }

    #[Test]
    public function the_management_scope_flags_a_strategy_from_notes_or_images(): void
    {
        Storage::fake('public');
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
    public function the_management_scope_lists_strategy_images_by_id_in_their_order(): void
    {
        Storage::fake('public');
        $boss = Boss::factory()->create();
        $first = $boss->addMedia(UploadedFile::fake()->image('first.png'))->toMediaCollection();
        $second = $boss->addMedia(UploadedFile::fake()->image('second.png'))->toMediaCollection();
        Media::setNewOrder([$second->id, $first->id]);

        $array = (new BossResource($boss->fresh()->load('media')))->scope('management')->resolve(new Request);

        $this->assertSame([
            ['id' => $second->id, 'url' => $second->getUrl()],
            ['id' => $first->id, 'url' => $first->getUrl()],
        ], $array['strategy_images']);
        $this->assertArrayNotHasKey('images', $array);
    }
}
