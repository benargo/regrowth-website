<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\BossResource;
use App\Http\Resources\RaidResource;
use App\Models\Boss;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BossResourceTest extends TestCase
{
    use RefreshDatabase;

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

    #[Test]
    public function it_returns_encounter_order(): void
    {
        $boss = Boss::factory()->order(3)->create();

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertSame(3, $array['encounter_order']);
    }

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

    #[Test]
    public function it_includes_items_when_loaded(): void
    {
        $boss = Boss::factory()->withItems(2)->create();
        $boss->load('items');

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertArrayHasKey('items', $array);
        $this->assertCount(2, $array['items']);
    }

    #[Test]
    public function it_excludes_items_when_not_loaded(): void
    {
        $boss = Boss::factory()->withItems(2)->create();

        $array = (new BossResource($boss))->resolve(new Request);

        $this->assertArrayNotHasKey('items', $array);
    }

    #[Test]
    public function it_includes_comments_when_loaded(): void
    {
        $boss = Boss::factory()->withComments(2)->create();
        $boss->load('comments');

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertArrayHasKey('comments', $array);
        $this->assertCount(2, $array['comments']);
    }

    #[Test]
    public function it_excludes_comments_when_not_loaded(): void
    {
        $boss = Boss::factory()->withComments(2)->create();

        $array = (new BossResource($boss))->resolve(new Request);

        $this->assertArrayNotHasKey('comments', $array);
    }

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

    #[Test]
    public function it_returns_slug(): void
    {
        $boss = Boss::factory()->create(['name' => 'Prince Malchezaar']);

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertSame('prince-malchezaar', $array['slug']);
    }

    #[Test]
    public function it_returns_images_array(): void
    {
        $boss = Boss::factory()->create();

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertArrayHasKey('images', $array);
        $this->assertIsArray($array['images']);
        $this->assertEmpty($array['images']);
    }

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
