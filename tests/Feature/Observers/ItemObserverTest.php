<?php

namespace Tests\Feature\Observers;

use App\Jobs\BuildAddonExportFile;
use App\Models\Item;
use App\Models\ItemPriority;
use App\Observers\ItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class ItemObserverTest extends TestCase
{
    use RefreshDatabase;

    // ==================== item ====================

    #[Test]
    public function saving_an_item_schedules_addon_export_build(): void
    {
        Bus::fake([BuildAddonExportFile::class]);

        Item::factory()->create();

        Bus::assertDispatched(BuildAddonExportFile::class, fn (BuildAddonExportFile $job) => $job->delay !== null);
    }

    #[Test]
    public function deleting_an_item_schedules_addon_export_build(): void
    {
        $item = Item::factory()->create();
        Bus::fake([BuildAddonExportFile::class]);

        $item->delete();

        Bus::assertDispatched(BuildAddonExportFile::class, fn (BuildAddonExportFile $job) => $job->delay !== null);
    }

    #[Test]
    public function saving_an_item_flushes_loot_council_cache(): void
    {
        Cache::tags(['lootcouncil'])->put('test-key', 'test-value', 60);

        Item::factory()->create();

        $this->assertNull(Cache::tags(['lootcouncil'])->get('test-key'));
    }

    #[Test]
    public function deleting_an_item_flushes_loot_council_cache(): void
    {
        $item = Item::factory()->create();
        Cache::tags(['lootcouncil'])->put('test-key', 'test-value', 60);

        $item->delete();

        $this->assertNull(Cache::tags(['lootcouncil'])->get('test-key'));
    }

    // ==================== item priority ====================

    #[Test]
    public function saving_an_item_priority_schedules_addon_export_build(): void
    {
        $item = Item::factory()->create();
        Bus::fake([BuildAddonExportFile::class]);

        ItemPriority::factory()->for($item)->create();

        Bus::assertDispatched(BuildAddonExportFile::class, fn (BuildAddonExportFile $job) => $job->delay !== null);
    }

    #[Test]
    public function deleting_an_item_priority_schedules_addon_export_build(): void
    {
        $itemPriority = ItemPriority::factory()->create();
        Bus::fake([BuildAddonExportFile::class]);

        $itemPriority->delete();

        Bus::assertDispatched(BuildAddonExportFile::class, fn (BuildAddonExportFile $job) => $job->delay !== null);
    }

    #[Test]
    public function saving_an_item_priority_flushes_loot_council_cache(): void
    {
        $item = Item::factory()->create();
        Cache::tags(['lootcouncil'])->put('test-key', 'test-value', 60);

        ItemPriority::factory()->for($item)->create();

        $this->assertNull(Cache::tags(['lootcouncil'])->get('test-key'));
    }

    #[Test]
    public function deleting_an_item_priority_flushes_loot_council_cache(): void
    {
        $itemPriority = ItemPriority::factory()->create();
        Cache::tags(['lootcouncil'])->put('test-key', 'test-value', 60);

        $itemPriority->delete();

        $this->assertNull(Cache::tags(['lootcouncil'])->get('test-key'));
    }

    // ==================== registration ====================

    #[Test]
    public function observer_is_registered_on_item_and_item_priority_models(): void
    {
        foreach ([Item::class, ItemPriority::class] as $model) {
            $attributes = (new \ReflectionClass($model))->getAttributes(ObservedBy::class);

            $this->assertNotEmpty($attributes, "{$model} has no #[ObservedBy] attribute.");
            $this->assertContains(ItemObserver::class, (array) $attributes[0]->getArguments()[0]);
        }
    }
}
