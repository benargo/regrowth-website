<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\RaidBossesCollection;
use App\Models\Boss;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RaidBossesCollectionTest extends TestCase
{
    use RefreshDatabase;

    // ==================== toArray ====================

    #[Test]
    public function it_returns_a_data_key(): void
    {
        $boss = Boss::factory()->create();

        $array = (new RaidBossesCollection(Boss::with('raid')->get()))->toArray(new Request);

        $this->assertArrayHasKey('data', $array);
    }

    #[Test]
    public function it_returns_a_raid_ids_key(): void
    {
        $raid = Raid::factory()->create();
        Boss::factory()->create(['raid_id' => $raid->id]);

        $array = (new RaidBossesCollection(Boss::all()))->toArray(new Request);

        $this->assertArrayHasKey('raid_ids', $array);
    }

    #[Test]
    public function it_groups_bosses_by_raid_id_under_data(): void
    {
        $raid1 = Raid::factory()->create();
        $raid2 = Raid::factory()->create();
        Boss::factory()->create(['raid_id' => $raid1->id]);
        Boss::factory()->create(['raid_id' => $raid2->id]);

        $json = json_decode((new RaidBossesCollection(Boss::all()))->response()->content());

        $this->assertObjectHasProperty((string) $raid1->id, $json->data);
        $this->assertObjectHasProperty((string) $raid2->id, $json->data);
    }

    #[Test]
    public function it_groups_multiple_bosses_under_the_same_raid_id(): void
    {
        $raid = Raid::factory()->create();
        Boss::factory()->count(3)->create(['raid_id' => $raid->id]);

        $array = (new RaidBossesCollection(Boss::all()))->toArray(new Request);

        $this->assertArrayHasKey($raid->id, $array['data']);
        $this->assertCount(3, $array['data'][$raid->id]);
    }

    #[Test]
    public function it_returns_boss_arrays_not_resource_objects(): void
    {
        $raid = Raid::factory()->create();
        Boss::factory()->create(['raid_id' => $raid->id]);

        $array = (new RaidBossesCollection(Boss::all()))->toArray(new Request);

        $this->assertIsArray($array['data'][$raid->id][0]);
        $this->assertArrayHasKey('id', $array['data'][$raid->id][0]);
        $this->assertArrayHasKey('name', $array['data'][$raid->id][0]);
        $this->assertArrayHasKey('notes', $array['data'][$raid->id][0]);
        $this->assertArrayHasKey('images', $array['data'][$raid->id][0]);
    }

    #[Test]
    public function it_omits_items_and_comments_when_relationships_are_not_loaded(): void
    {
        $raid = Raid::factory()->create();
        Boss::factory()->create(['raid_id' => $raid->id]);

        $array = (new RaidBossesCollection(Boss::all()))->toArray(new Request);

        $this->assertArrayNotHasKey('items', $array['data'][$raid->id][0]);
        $this->assertArrayNotHasKey('comments', $array['data'][$raid->id][0]);
    }

    #[Test]
    public function it_populates_raid_ids_with_unique_raid_ids(): void
    {
        $raid1 = Raid::factory()->create();
        $raid2 = Raid::factory()->create();
        Boss::factory()->count(2)->create(['raid_id' => $raid1->id]);
        Boss::factory()->create(['raid_id' => $raid2->id]);

        $array = (new RaidBossesCollection(Boss::all()))->toArray(new Request);

        $this->assertCount(2, $array['raid_ids']);
        $this->assertContains($raid1->id, $array['raid_ids']);
        $this->assertContains($raid2->id, $array['raid_ids']);
    }

    #[Test]
    public function it_returns_empty_data_and_raid_ids_for_empty_collection(): void
    {
        $array = (new RaidBossesCollection(collect()))->toArray(new Request);

        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('raid_ids', $array);
        $this->assertEmpty($array['data']);
        $this->assertEmpty($array['raid_ids']);
    }
}
