<?php

namespace Tests\Feature\Database\Migrations;

use App\Models\GameVersion;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class PivotGameVersionsPlayableRacesAndClassesMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function up_creates_the_pivot_game_versions_playable_races_table_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('pivot_game_versions_playable_races', [
            'game_version_id',
            'playable_race_id',
            'created_at',
            'updated_at',
        ]));
    }

    #[Test]
    public function up_creates_the_pivot_game_versions_playable_classes_table_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('pivot_game_versions_playable_classes', [
            'game_version_id',
            'playable_class_id',
            'created_at',
            'updated_at',
        ]));
    }

    #[Test]
    public function deleting_a_game_version_cascades_to_the_playable_races_pivot_without_deleting_the_playable_race(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $playableRace = PlayableRace::factory()->create();

        DB::table('pivot_game_versions_playable_races')->insert([
            'game_version_id' => $gameVersion->id,
            'playable_race_id' => $playableRace->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gameVersion->delete();

        $this->assertDatabaseHas('playable_races', ['id' => $playableRace->id]);
        $this->assertDatabaseMissing('pivot_game_versions_playable_races', [
            'game_version_id' => $gameVersion->id,
            'playable_race_id' => $playableRace->id,
        ]);
    }

    #[Test]
    public function deleting_a_game_version_cascades_to_the_playable_classes_pivot_without_deleting_the_playable_class(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $playableClass = PlayableClass::factory()->create();

        DB::table('pivot_game_versions_playable_classes')->insert([
            'game_version_id' => $gameVersion->id,
            'playable_class_id' => $playableClass->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gameVersion->delete();

        $this->assertDatabaseHas('playable_classes', ['id' => $playableClass->id]);
        $this->assertDatabaseMissing('pivot_game_versions_playable_classes', [
            'game_version_id' => $gameVersion->id,
            'playable_class_id' => $playableClass->id,
        ]);
    }
}
