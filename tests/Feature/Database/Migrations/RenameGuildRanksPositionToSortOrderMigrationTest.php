<?php

namespace Tests\Feature\Database\Migrations;

use App\Models\GuildRank;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RenameGuildRanksPositionToSortOrderMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function migration(): Migration
    {
        return require database_path('migrations/2026_08_28_151523_rename_guild_ranks_position_to_sort_order.php');
    }

    #[Test]
    public function up_renames_the_column_and_preserves_values(): void
    {
        $rank = GuildRank::factory()->create(['sort_order' => 3]);

        $this->assertTrue(Schema::hasColumn('guild_ranks', 'sort_order'));
        $this->assertFalse(Schema::hasColumn('guild_ranks', 'position'));
        $this->assertSame(3, $rank->fresh()->sort_order);
    }

    #[Test]
    public function up_drops_the_unique_index(): void
    {
        GuildRank::factory()->create(['sort_order' => 0, 'name' => 'Guild Master']);
        $duplicate = GuildRank::factory()->create(['sort_order' => 0, 'name' => 'Officer']);

        $this->assertModelExists($duplicate);
        $this->assertDatabaseCount('guild_ranks', 2);
    }

    #[Test]
    public function down_restores_the_position_column_and_up_reapplies(): void
    {
        $rank = GuildRank::factory()->create(['sort_order' => 4]);

        $this->migration()->down();

        $this->assertTrue(Schema::hasColumn('guild_ranks', 'position'));
        $this->assertFalse(Schema::hasColumn('guild_ranks', 'sort_order'));

        GuildRank::query()->insert(['position' => 5, 'name' => 'Second', 'count_attendance' => true]);

        try {
            GuildRank::query()->insert(['position' => 5, 'name' => 'Duplicate', 'count_attendance' => true]);
            $this->fail('Expected the unique index on position to be restored.');
        } catch (QueryException) {
            // Unique index restored as expected.
        }

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('guild_ranks', 'sort_order'));
        $this->assertFalse(Schema::hasColumn('guild_ranks', 'position'));
        $this->assertSame(4, $rank->fresh()->sort_order);
    }
}
