<?php

namespace Tests\Feature\LootCouncil;

use App\Events\ItemPrioritySaved;
use App\Jobs\RebuildLootCouncilCache;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CacheRebuildTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_flushes_cache_when_event_fires(): void
    {
        Queue::fake([RebuildLootCouncilCache::class]);

        Cache::tags(['lootcouncil'])->put('test_key', 'test_value', now()->addHour());
        $this->assertTrue(Cache::tags(['lootcouncil'])->has('test_key'));

        ItemPrioritySaved::dispatch();

        $this->assertFalse(Cache::tags(['lootcouncil'])->has('test_key'));
    }

    public function test_listener_dispatches_rebuild_job_when_event_fires(): void
    {
        Queue::fake([RebuildLootCouncilCache::class]);

        ItemPrioritySaved::dispatch();

        Queue::assertPushed(RebuildLootCouncilCache::class);
    }

    public function test_rebuild_job_populates_priorities_cache(): void
    {
        Priority::factory()->count(3)->create();

        Cache::tags(['lootcouncil'])->flush();
        $this->assertFalse(Cache::tags(['lootcouncil'])->has('priorities.all'));

        $job = new RebuildLootCouncilCache;
        $job->handle();

        $this->assertTrue(Cache::tags(['lootcouncil'])->has('priorities.all'));

        $cached = Cache::tags(['lootcouncil'])->get('priorities.all');
        $this->assertCount(3, $cached);
    }

    public function test_rebuild_job_populates_bosses_cache(): void
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        Boss::factory()->count(3)->create(['raid_id' => $raid->id]);

        // Ensure cache is empty
        Cache::tags(['lootcouncil'])->flush();
        $this->assertFalse(Cache::tags(['lootcouncil'])->has('bosses.tbc.with_comments'));

        $job = new RebuildLootCouncilCache;
        $job->handle();

        $this->assertTrue(Cache::tags(['lootcouncil'])->has('bosses.tbc.with_comments'));
    }

    public function test_rebuild_job_populates_boss_items_cache(): void
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        Item::factory()->count(2)->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);

        // Ensure cache is empty
        Cache::tags(['lootcouncil'])->flush();
        $cacheKey = "loot_items.boss_{$boss->id}.index";
        $this->assertFalse(Cache::tags(['lootcouncil'])->has($cacheKey));

        $job = new RebuildLootCouncilCache;
        $job->handle();

        $this->assertTrue(Cache::tags(['lootcouncil'])->has($cacheKey));
    }

    public function test_rebuild_job_populates_trash_items_cache(): void
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        Item::factory()->count(2)->create(['raid_id' => $raid->id, 'boss_id' => null]);

        // Ensure cache is empty
        Cache::tags(['lootcouncil'])->flush();
        $cacheKey = "loot_items.trash_raid_{$raid->id}.index";
        $this->assertFalse(Cache::tags(['lootcouncil'])->has($cacheKey));

        $job = new RebuildLootCouncilCache;
        $job->handle();

        $this->assertTrue(Cache::tags(['lootcouncil'])->has($cacheKey));
    }

    public function test_bosses_cache_includes_comment_counts(): void
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        $item = Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
        $user = User::factory()->create();
        $item->comments()->create(['user_id' => $user->id, 'body' => 'Test comment']);

        Cache::tags(['lootcouncil'])->flush();

        $job = new RebuildLootCouncilCache;
        $job->handle();

        $cachedBosses = Cache::tags(['lootcouncil'])->get('bosses.tbc.with_comments');

        $this->assertNotNull($cachedBosses);
        $this->assertArrayHasKey($raid->id, $cachedBosses);

        $bossData = collect($cachedBosses[$raid->id])->firstWhere('id', $boss->id);
        $this->assertEquals(1, $bossData['comments_count']);
    }
}
