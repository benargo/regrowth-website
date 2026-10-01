<?php

namespace Tests\Feature\Models;

use App\Models\GameVersion;
use App\Models\Item;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemUuidPrimaryKeyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function items_id_column_is_a_uuid_primary_key(): void
    {
        $item = Item::factory()->create();

        $this->assertTrue(Str::isUuid($item->id));
        $this->assertSame('string', $item->getKeyType());
        $this->assertFalse($item->getIncrementing());
    }

    #[Test]
    public function items_table_has_blizzard_id_and_game_version_id_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('items', 'blizzard_id'));
        $this->assertTrue(Schema::hasColumn('items', 'game_version_id'));
        $this->assertFalse(Schema::hasColumn('items', 'raid_id'));
    }

    #[Test]
    public function the_same_blizzard_id_can_exist_under_two_different_game_versions(): void
    {
        $versionA = GameVersion::factory()->create();
        $versionB = GameVersion::factory()->create();

        $itemA = Item::factory()->create(['blizzard_id' => 12345, 'game_version_id' => $versionA->id]);
        $itemB = Item::factory()->create(['blizzard_id' => 12345, 'game_version_id' => $versionB->id]);

        $this->assertNotSame($itemA->id, $itemB->id);
        $this->assertDatabaseHas('items', ['id' => $itemA->id, 'blizzard_id' => 12345, 'game_version_id' => $versionA->id]);
        $this->assertDatabaseHas('items', ['id' => $itemB->id, 'blizzard_id' => 12345, 'game_version_id' => $versionB->id]);
    }

    #[Test]
    public function duplicate_blizzard_id_within_the_same_game_version_is_rejected(): void
    {
        $version = GameVersion::factory()->create();
        Item::factory()->create(['blizzard_id' => 999, 'game_version_id' => $version->id]);

        $this->expectException(QueryException::class);

        Item::factory()->create(['blizzard_id' => 999, 'game_version_id' => $version->id]);
    }

    #[Test]
    public function deleting_a_game_version_nulls_the_items_game_version_id(): void
    {
        $version = GameVersion::factory()->create();
        $item = Item::factory()->create(['game_version_id' => $version->id]);

        $version->delete();

        $this->assertDatabaseHas('items', ['id' => $item->id, 'game_version_id' => null]);
    }
}
