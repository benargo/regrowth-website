<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\BossResource;
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
    public function it_includes_raid_when_loaded(): void
    {
        $boss = Boss::factory()->create();
        $boss->load('raid');

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertArrayHasKey('raid', $array);
        $this->assertInstanceOf(Raid::class, $array['raid']);
    }

    #[Test]
    public function it_excludes_raid_when_not_loaded(): void
    {
        $boss = Boss::factory()->create();

        $array = (new BossResource($boss))->resolve(new Request);

        $this->assertArrayNotHasKey('raid', $array);
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
    public function it_returns_all_expected_keys(): void
    {
        $boss = Boss::factory()->create();

        $array = (new BossResource($boss))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('encounter_order', $array);
    }
}
