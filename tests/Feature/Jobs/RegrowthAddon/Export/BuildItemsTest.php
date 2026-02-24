<?php

namespace Tests\Feature\Jobs\RegrowthAddon\Export;

use App\Jobs\RegrowthAddon\Export\BuildItems;
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

class BuildItemsTest extends TestCase
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
        $this->assertInstanceOf(ShouldQueue::class, new BuildItems);
    }

    public function test_it_uses_batchable_trait(): void
    {
        $this->assertContains(Batchable::class, class_uses_recursive(BuildItems::class));
    }

    public function test_it_has_correct_job_tags(): void
    {
        $this->assertEquals(['regrowth-addon', 'regrowth-addon:build'], (new BuildItems)->tags());
    }

    public function test_it_has_skip_if_batch_cancelled_middleware(): void
    {
        $middlewareClasses = array_map(fn ($m) => get_class($m), (new BuildItems)->middleware());

        $this->assertContains(SkipIfBatchCancelled::class, $middlewareClasses);
    }

    // ==========================================
    // Cache Tests
    // ==========================================

    public function test_it_caches_items_under_correct_key_and_tags(): void
    {
        $item = Item::factory()->create();
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $this->assertNotNull($cached);
    }

    // ==========================================
    // Item Data Tests
    // ==========================================

    public function test_it_only_includes_items_with_priorities(): void
    {
        $itemWithPriorities = Item::factory()->create();
        $itemWithoutPriorities = Item::factory()->create();
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $itemWithPriorities->id, 'priority_id' => $priority->id]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $itemIds = collect($cached)->pluck('item_id')->toArray();
        $this->assertContains($itemWithPriorities->id, $itemIds);
        $this->assertNotContains($itemWithoutPriorities->id, $itemIds);
    }

    public function test_it_includes_item_priorities_with_weight(): void
    {
        $item = Item::factory()->create();
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
            'weight' => 75,
        ]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $itemData = collect($cached)->firstWhere('item_id', $item->id);
        $itemPriorityData = collect($itemData['priorities'])->firstWhere('priority_id', $priority->id);
        $this->assertEquals(75, $itemPriorityData['weight']);
    }

    // ==========================================
    // Clean Notes Tests
    // ==========================================

    public function test_it_cleans_notes_by_removing_wowhead_links(): void
    {
        $item = Item::factory()->create(['notes' => 'Get !wh[Thunderfury](item=19019) from the boss']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $itemData = collect($cached)->firstWhere('item_id', $item->id);
        $this->assertEquals('Get Thunderfury from the boss', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_markdown_links(): void
    {
        $item = Item::factory()->create(['notes' => 'Check [this guide](https://example.com) for details']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $itemData = collect($cached)->firstWhere('item_id', $item->id);
        $this->assertEquals('Check this guide for details', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_bold_formatting(): void
    {
        $item = Item::factory()->create(['notes' => 'This is **very important** information']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $itemData = collect($cached)->firstWhere('item_id', $item->id);
        $this->assertEquals('This is very important information', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_italic_formatting(): void
    {
        $item = Item::factory()->create(['notes' => 'This is *emphasized* text']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $itemData = collect($cached)->firstWhere('item_id', $item->id);
        $this->assertEquals('This is emphasized text', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_underline_formatting(): void
    {
        $item = Item::factory()->create(['notes' => 'This is __underlined__ text']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $itemData = collect($cached)->firstWhere('item_id', $item->id);
        $this->assertEquals('This is underlined text', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_inline_code(): void
    {
        $item = Item::factory()->create(['notes' => 'Use the `command` here']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $itemData = collect($cached)->firstWhere('item_id', $item->id);
        $this->assertEquals('Use the command here', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_headers(): void
    {
        $item = Item::factory()->create(['notes' => '## Section Title']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $itemData = collect($cached)->firstWhere('item_id', $item->id);
        $this->assertEquals('Section Title', $itemData['notes']);
    }

    public function test_it_cleans_notes_by_removing_strikethrough(): void
    {
        $item = Item::factory()->create(['notes' => 'This is ~~deleted~~ text']);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $itemData = collect($cached)->firstWhere('item_id', $item->id);
        $this->assertEquals('This is deleted text', $itemData['notes']);
    }

    public function test_it_returns_null_for_null_notes(): void
    {
        $item = Item::factory()->create(['notes' => null]);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $itemData = collect($cached)->firstWhere('item_id', $item->id);
        $this->assertNull($itemData['notes']);
    }

    public function test_it_normalizes_whitespace_in_notes(): void
    {
        $item = Item::factory()->create(['notes' => "Multiple   spaces\nand\nnewlines"]);
        $priority = Priority::factory()->create();
        ItemPriority::factory()->create(['item_id' => $item->id, 'priority_id' => $priority->id]);

        (new BuildItems)->handle();

        $cached = Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->get('regrowth-addon.build.items');

        $itemData = collect($cached)->firstWhere('item_id', $item->id);
        $this->assertEquals('Multiple spaces and newlines', $itemData['notes']);
    }

    // ==========================================
    // Failure Handling Tests
    // ==========================================

    public function test_it_logs_error_on_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'BuildItems job failed.'
                    && isset($context['error']);
            });

        $job = new BuildItems;
        $job->failed(new \RuntimeException('Test failure'));
    }
}
