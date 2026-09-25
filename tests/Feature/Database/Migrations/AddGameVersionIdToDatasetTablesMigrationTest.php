<?php

namespace Tests\Feature\Database\Migrations;

use App\Models\GuildTag;
use App\Models\Phase;
use App\Models\Zone;
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
    public function up_does_not_add_a_game_version_id_column_to_tables_that_inherit_it_through_their_phase(string $table): void
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

    #[Test]
    public function up_adds_a_nullable_game_version_id_column_to_wcl_guild_tags(): void
    {
        $this->assertTrue(Schema::hasColumn('wcl_guild_tags', 'game_version_id'));

        $guildTag = GuildTag::factory()->create();

        $this->assertNull($guildTag->game_version_id);
    }

    #[Test]
    public function up_adds_a_nullable_game_version_id_column_to_wcl_zones(): void
    {
        $this->assertTrue(Schema::hasColumn('wcl_zones', 'game_version_id'));

        $zone = Zone::factory()->create();

        $this->assertNull($zone->game_version_id);
    }
}
