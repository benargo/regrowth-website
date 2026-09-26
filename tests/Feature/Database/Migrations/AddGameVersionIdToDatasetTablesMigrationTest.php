<?php

namespace Tests\Feature\Database\Migrations;

use App\Models\Phase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('platform')]
class AddGameVersionIdToDatasetTablesMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestWith(['bosses'])]
    #[TestWith(['raids'])]
    #[TestWith(['wcl_guild_tags'])]
    #[TestWith(['wcl_zones'])]
    public function up_does_not_add_a_game_version_id_column_to_tables_without_a_direct_version(string $table): void
    {
        $this->assertFalse(Schema::hasColumn($table, 'game_version_id'));
    }

    #[Test]
    public function up_adds_a_nullable_game_version_id_column_to_phases(): void
    {
        $this->assertTrue(Schema::hasColumn('phases', 'game_version_id'));

        $phase = Phase::factory()->create();

        $this->assertNull($phase->game_version_id);
    }
}
