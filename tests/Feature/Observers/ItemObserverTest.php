<?php

namespace Tests\Feature\Observers;

use App\Jobs\Addon\BuildExportFile;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\ItemPriority;
use App\Observers\ItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ReleasesAddonExportLocks;
use Tests\TestCase;

#[Group('loot')]
class ItemObserverTest extends TestCase
{
    use RefreshDatabase;
    use ReleasesAddonExportLocks;

    // ==================== item ====================

    #[Test]
    public function saving_an_item_schedules_addon_export_build(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $this->releaseAddonExportLocks();
        Bus::fake([BuildExportFile::class]);

        Item::factory()->create(['game_version_id' => $gameVersion->id]);

        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $gameVersion->id && $job->delay !== null);
    }

    #[Test]
    public function deleting_an_item_schedules_addon_export_build(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $item = Item::factory()->create(['game_version_id' => $gameVersion->id]);
        $this->releaseAddonExportLocks();
        Bus::fake([BuildExportFile::class]);

        $item->delete();

        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $gameVersion->id && $job->delay !== null);
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

    #[Test]
    public function moving_an_item_to_another_game_version_rebuilds_both(): void
    {
        $from = GameVersion::factory()->fetchableRoster()->create();
        $to = GameVersion::factory()->fetchableRoster()->create();
        $item = Item::factory()->create(['game_version_id' => $from->id]);
        $this->releaseAddonExportLocks();
        Bus::fake([BuildExportFile::class]);

        $item->update(['game_version_id' => $to->id]);

        Bus::assertDispatchedTimes(BuildExportFile::class, 2);
        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $from->id);
        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $to->id);
    }

    // ==================== item priority ====================

    #[Test]
    public function saving_an_item_priority_schedules_addon_export_build(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $item = Item::factory()->create(['game_version_id' => $gameVersion->id]);
        $this->releaseAddonExportLocks();
        Bus::fake([BuildExportFile::class]);

        ItemPriority::factory()->for($item)->create();

        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $gameVersion->id && $job->delay !== null);
    }

    #[Test]
    public function saving_an_item_priority_rebuilds_its_items_game_version(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $other = GameVersion::factory()->fetchableRoster()->create();
        $item = Item::factory()->create(['game_version_id' => $gameVersion->id]);
        Item::factory()->create(['game_version_id' => $other->id]);
        $this->releaseAddonExportLocks();
        Bus::fake([BuildExportFile::class]);

        ItemPriority::factory()->for($item)->create();

        Bus::assertDispatchedTimes(BuildExportFile::class, 1);
        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $gameVersion->id);
    }

    #[Test]
    public function deleting_an_item_priority_schedules_addon_export_build(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $item = Item::factory()->create(['game_version_id' => $gameVersion->id]);
        $itemPriority = ItemPriority::factory()->for($item)->create();
        $this->releaseAddonExportLocks();
        Bus::fake([BuildExportFile::class]);

        $itemPriority->delete();

        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $gameVersion->id && $job->delay !== null);
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
