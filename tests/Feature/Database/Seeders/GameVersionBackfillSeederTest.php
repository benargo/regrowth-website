<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\Boss;
use App\Models\GameVersion;
use App\Models\GuildTag;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Raid;
use App\Models\Zone;
use Database\Seeders\GameVersionBackfillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('platform')]
class GameVersionBackfillSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_assigns_the_target_game_version_to_every_row_missing_one_and_attaches_reference_models(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $boss = Boss::factory()->create(['game_version_id' => null]);
        $phase = Phase::factory()->create(['game_version_id' => null]);
        $raid = Raid::factory()->create(['game_version_id' => null]);
        $guildTag = GuildTag::factory()->create(['game_version_id' => null]);
        $zone = Zone::factory()->create(['game_version_id' => null]);

        $race = PlayableRace::factory()->create();
        $class = PlayableClass::factory()->create();

        $this->runSeeder($gameVersion->id);

        $this->assertSame($gameVersion->id, $boss->fresh()->game_version_id);
        $this->assertSame($gameVersion->id, $phase->fresh()->game_version_id);
        $this->assertSame($gameVersion->id, $raid->fresh()->game_version_id);
        $this->assertSame($gameVersion->id, $guildTag->fresh()->game_version_id);
        $this->assertSame($gameVersion->id, $zone->fresh()->game_version_id);

        $this->assertDatabaseHas('pivot_game_versions_playable_races', [
            'game_version_id' => $gameVersion->id,
            'playable_race_id' => $race->id,
        ]);
        $this->assertDatabaseHas('pivot_game_versions_playable_classes', [
            'game_version_id' => $gameVersion->id,
            'playable_class_id' => $class->id,
        ]);
    }

    #[Test]
    public function it_is_idempotent_on_a_second_run(): void
    {
        $gameVersion = GameVersion::factory()->create();

        Boss::factory()->create(['game_version_id' => null]);
        Phase::factory()->create(['game_version_id' => null]);
        Raid::factory()->create(['game_version_id' => null]);
        GuildTag::factory()->create(['game_version_id' => null]);
        Zone::factory()->create(['game_version_id' => null]);
        PlayableRace::factory()->create();
        PlayableClass::factory()->create();

        $this->runSeeder($gameVersion->id);

        $bossCount = Boss::count();
        $phaseCount = Phase::count();
        $raidCount = Raid::count();
        $guildTagCount = GuildTag::count();
        $zoneCount = Zone::count();
        $racePivotCount = $gameVersion->playableRaces()->count();
        $classPivotCount = $gameVersion->playableClasses()->count();

        $this->runSeeder($gameVersion->id);

        $this->assertSame($bossCount, Boss::count());
        $this->assertSame($phaseCount, Phase::count());
        $this->assertSame($raidCount, Raid::count());
        $this->assertSame($guildTagCount, GuildTag::count());
        $this->assertSame($zoneCount, Zone::count());
        $this->assertSame($racePivotCount, $gameVersion->playableRaces()->count());
        $this->assertSame($classPivotCount, $gameVersion->playableClasses()->count());
    }

    #[Test]
    public function it_does_not_touch_rows_already_assigned_to_a_different_game_version(): void
    {
        $targetVersion = GameVersion::factory()->create();
        $otherVersion = GameVersion::factory()->create();

        $boss = Boss::factory()->create(['game_version_id' => $otherVersion->id]);

        $this->runSeeder($targetVersion->id);

        $this->assertSame($otherVersion->id, $boss->fresh()->game_version_id);
    }

    #[Test]
    public function it_throws_when_no_game_version_id_is_set_and_no_game_version_exists(): void
    {
        $this->expectException(RuntimeException::class);

        $this->runSeeder();
    }

    #[Test]
    public function it_throws_when_no_game_version_id_is_set_and_multiple_game_versions_exist(): void
    {
        GameVersion::factory()->count(2)->create();

        $this->expectException(RuntimeException::class);

        $this->runSeeder();
    }

    private function runSeeder(?int $gameVersionId = null): void
    {
        $seeder = app(GameVersionBackfillSeeder::class);
        $seeder->gameVersionId = $gameVersionId;
        $seeder->run();
    }
}
