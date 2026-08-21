<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\BossResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\RaidResource;
use App\Models\Boss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
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

        $this->mockItemService();
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

    // ==================== encounter order ====================

    #[Test]
    public function it_returns_encounter_order(): void
    {
        $boss = Boss::factory()->order(3)->create();

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertSame(3, $array['encounter_order']);
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
        $this->assertArrayHasKey('encounter_order', $array);
    }
}
