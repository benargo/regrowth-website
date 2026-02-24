<?php

namespace Tests\Feature\Jobs\RegrowthAddon\Export;

use App\Jobs\RegrowthAddon\Export\BuildPriorities;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemPriority;
use App\Models\LootCouncil\Priority;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BuildPrioritiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
    }

    // ==========================================
    // Job Contract Tests
    // ==========================================

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new BuildPriorities);
    }

    public function test_it_uses_batchable_trait(): void
    {
        $this->assertContains(Batchable::class, class_uses_recursive(BuildPriorities::class));
    }

    public function test_it_has_correct_job_tags(): void
    {
        $this->assertEquals(['regrowth-addon', 'regrowth-addon:build'], (new BuildPriorities)->tags());
    }

    public function test_it_has_skip_if_batch_cancelled_middleware(): void
    {
        $middlewareClasses = array_map(fn ($m) => get_class($m), (new BuildPriorities)->middleware());

        $this->assertContains(SkipIfBatchCancelled::class, $middlewareClasses);
    }

    // ==========================================
    // Cache Tests
    // ==========================================

    public function test_it_caches_priorities_under_correct_key_and_tags(): void
    {
        $priority = Priority::factory()->create(['title' => 'Tank']);
        $item = Item::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildPriorities)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.priorities');

        $this->assertNotNull($cached);
    }

    // ==========================================
    // Priority Data Tests
    // ==========================================

    public function test_it_only_includes_priorities_with_items(): void
    {
        $priorityWithItems = Priority::factory()->create(['title' => 'Tank']);
        $priorityWithoutItems = Priority::factory()->create(['title' => 'Healer']);
        $item = Item::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priorityWithItems->id]);

        (new BuildPriorities)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.priorities');

        $ids = collect($cached)->pluck('id')->toArray();
        $this->assertContains($priorityWithItems->id, $ids);
        $this->assertNotContains($priorityWithoutItems->id, $ids);
    }

    public function test_it_includes_priority_icon_from_media(): void
    {
        $priority = Priority::factory()->create([
            'title' => 'Tank',
            'media' => [
                'media_type' => 'spell',
                'media_id' => 12345,
                'media_name' => 'spell_nature_strength',
            ],
        ]);
        $item = Item::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildPriorities)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.priorities');

        $priorityData = collect($cached)->firstWhere('id', $priority->id);
        $this->assertEquals('spell_nature_strength', $priorityData['icon']);
    }

    public function test_it_returns_null_icon_when_media_name_missing(): void
    {
        $priority = Priority::factory()->create([
            'title' => 'Tank',
            'media' => ['media_type' => 'spell', 'media_id' => 12345],
        ]);
        $item = Item::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildPriorities)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.priorities');

        $priorityData = collect($cached)->firstWhere('id', $priority->id);
        $this->assertNull($priorityData['icon']);
    }

    public function test_it_includes_priority_id_and_name(): void
    {
        $priority = Priority::factory()->create(['title' => 'Warlock']);
        $item = Item::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildPriorities)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.priorities');

        $priorityData = collect($cached)->firstWhere('id', $priority->id);
        $this->assertEquals($priority->id, $priorityData['id']);
        $this->assertEquals('Warlock', $priorityData['name']);
    }

    // ==========================================
    // Failure Handling Tests
    // ==========================================

    public function test_it_logs_error_on_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'BuildPriorities job failed.'
                    && isset($context['error']);
            });

        $job = new BuildPriorities;
        $job->failed(new \RuntimeException('Test failure'));
    }
}
