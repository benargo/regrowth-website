<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\UpdateGameVersion;
use App\Enums\Faction;
use App\Enums\Theme;
use App\Models\Boss;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
#[Group('raiding')]
class UpdateGameVersionTest extends TestCase
{
    use RefreshDatabase;

    // ==================== details ====================

    #[Group('happy-path')]
    #[Test]
    public function it_updates_only_the_fields_it_is_given(): void
    {
        $gameVersion = GameVersion::factory()->create([
            'title' => 'Era',
            'realm' => 'Gehennas',
            'faction' => Faction::HORDE,
            'theme' => Theme::FOREVER,
            'warcraftlogs_guild' => 123456,
        ]);

        $this->updateGameVersion($gameVersion, ['realm' => 'Firemaw']);

        $fresh = $gameVersion->fresh();
        $this->assertSame('Firemaw', $fresh->realm);
        $this->assertSame('Era', $fresh->title);
        $this->assertSame(Faction::HORDE, $fresh->faction);
        $this->assertSame(Theme::FOREVER, $fresh->theme);
        $this->assertSame(123456, $fresh->warcraftlogs_guild);
    }

    #[Test]
    public function it_updates_the_game_version_it_is_given_in_place(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Old title']);

        $this->updateGameVersion($gameVersion, ['title' => 'New title']);

        $this->assertSame('New title', $gameVersion->title);
    }

    #[Test]
    public function it_stores_null_for_a_field_given_as_null(): void
    {
        $gameVersion = GameVersion::factory()->create([
            'realm' => 'Gehennas',
            'faction' => Faction::HORDE,
            'warcraftlogs_guild' => 123456,
        ]);

        $this->updateGameVersion($gameVersion, [
            'faction' => null,
            'warcraftlogs_guild' => null,
        ]);

        $fresh = $gameVersion->fresh();
        $this->assertNull($fresh->faction);
        $this->assertNull($fresh->warcraftlogs_guild);
        $this->assertSame('Gehennas', $fresh->realm);
    }

    #[Test]
    public function it_stores_the_default_theme_for_a_theme_given_as_null(): void
    {
        config(['app.theme' => 'classic']);
        $gameVersion = GameVersion::factory()->create(['theme' => Theme::FOREVER]);

        $this->updateGameVersion($gameVersion, ['theme' => null]);

        $this->assertDatabaseHas('game_versions', ['id' => $gameVersion->id, 'theme' => Theme::CLASSIC->value]);
    }

    #[Test]
    public function it_leaves_relationships_alone_when_their_keys_are_not_given(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $race = PlayableRace::factory()->create();
        $gameVersion->playableRaces()->attach($race);
        $phase = Phase::factory()->for($gameVersion)->create();

        $this->updateGameVersion($gameVersion, ['realm' => 'Firemaw']);

        $this->assertSame([$race->id], $gameVersion->playableRaces()->pluck('playable_races.id')->all());
        $this->assertSame($gameVersion->id, $phase->fresh()->game_version_id);
    }

    // ==================== races and classes ====================

    #[Group('happy-path')]
    #[Test]
    public function it_replaces_the_linked_races_and_classes(): void
    {
        $gameVersion = GameVersion::factory()->create();
        [$previousRace, $newRace] = PlayableRace::factory()->count(2)->create();
        [$previousClass, $newClass] = PlayableClass::factory()->count(2)->create();
        $gameVersion->playableRaces()->attach($previousRace);
        $gameVersion->playableClasses()->attach($previousClass);

        $this->updateGameVersion($gameVersion, [
            'playable_race_ids' => [$newRace->id],
            'playable_class_ids' => [$newClass->id],
        ]);

        $this->assertSame([$newRace->id], $gameVersion->playableRaces()->pluck('playable_races.id')->all());
        $this->assertSame([$newClass->id], $gameVersion->playableClasses()->pluck('playable_classes.id')->all());
    }

    #[Test]
    public function it_unlinks_every_race_and_class_given_an_empty_list(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $gameVersion->playableRaces()->attach(PlayableRace::factory()->create());
        $gameVersion->playableClasses()->attach(PlayableClass::factory()->create());

        $this->updateGameVersion($gameVersion, [
            'playable_race_ids' => [],
            'playable_class_ids' => [],
        ]);

        $this->assertSame(0, $gameVersion->playableRaces()->count());
        $this->assertSame(0, $gameVersion->playableClasses()->count());
    }

    // ==================== phases ====================

    #[Group('happy-path')]
    #[Test]
    public function it_links_the_given_phases_and_unlinks_the_rest(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $kept = Phase::factory()->for($gameVersion)->create();
        $dropped = Phase::factory()->for($gameVersion)->create();
        $added = Phase::factory()->create();

        $this->updateGameVersion($gameVersion, ['phase_ids' => [$kept->id, $added->id]]);

        $this->assertSame($gameVersion->id, $kept->fresh()->game_version_id);
        $this->assertSame($gameVersion->id, $added->fresh()->game_version_id);
        $this->assertNull($dropped->fresh()->game_version_id);
    }

    #[Test]
    public function it_moves_a_phase_from_another_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $otherGameVersion = GameVersion::factory()->create();
        $moved = Phase::factory()->for($otherGameVersion)->create();
        $untouched = Phase::factory()->for($otherGameVersion)->create();

        $this->updateGameVersion($gameVersion, ['phase_ids' => [$moved->id]]);

        $this->assertSame($gameVersion->id, $moved->fresh()->game_version_id);
        $this->assertSame($otherGameVersion->id, $untouched->fresh()->game_version_id);
    }

    #[Test]
    public function it_unlinks_every_phase_given_an_empty_list(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->for($gameVersion)->create();

        $this->updateGameVersion($gameVersion, ['phase_ids' => []]);

        $this->assertNull($phase->fresh()->game_version_id);
    }

    // ==================== new phase ====================

    #[Group('happy-path')]
    #[Test]
    public function it_creates_a_phase_owned_by_the_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $this->updateGameVersion($gameVersion, [
            'new_phase' => [
                'number' => '2.5',
                'description' => "Zul'Aman",
                'start_date' => '2026-05-12',
            ],
        ]);

        $phase = Phase::sole();
        $this->assertSame($gameVersion->id, $phase->game_version_id);
        $this->assertSame('2.5', $phase->number);
        $this->assertSame("Zul'Aman", $phase->description);
        $this->assertSame('2026-05-12', $phase->start_date->toDateString());
    }

    #[Test]
    public function it_keeps_a_new_phase_when_the_same_update_unlinks_every_phase(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $this->updateGameVersion($gameVersion, [
            'phase_ids' => [],
            'new_phase' => ['number' => '1.0', 'description' => 'Launch'],
        ]);

        $this->assertSame($gameVersion->id, Phase::sole()->game_version_id);
    }

    // ==================== raids and bosses follow their phase ====================

    #[Group('happy-path')]
    #[Test]
    public function it_brings_a_phases_raids_and_bosses_along_when_the_phase_is_linked(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->create();
        $raid = Raid::factory()->for($phase)->create();
        $boss = Boss::factory()->for($raid)->create();

        $this->updateGameVersion($gameVersion, ['phase_ids' => [$phase->id]]);

        $this->assertTrue($gameVersion->raids()->whereKey($raid->id)->exists());
        $this->assertTrue($boss->fresh()->raid->phase->gameVersion->is($gameVersion));
    }

    #[Test]
    public function it_drops_a_phases_raids_when_the_phase_is_left_out(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->for($gameVersion)->create();
        Raid::factory()->for($phase)->create();

        $this->updateGameVersion($gameVersion, ['phase_ids' => []]);

        $this->assertNull($phase->fresh()->game_version_id);
        $this->assertFalse($gameVersion->raids()->exists());
    }

    // ==================== new raid ====================

    #[Group('happy-path')]
    #[Test]
    public function it_creates_a_raid_under_the_given_phase(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->for($gameVersion)->create();

        $this->updateGameVersion($gameVersion, [
            'new_raid' => [
                'name' => 'Karazhan',
                'difficulty' => 'Normal',
                'phase_id' => $phase->id,
                'max_players' => 10,
            ],
        ]);

        $raid = Raid::sole();
        $this->assertSame($phase->id, $raid->phase_id);
        $this->assertSame('Karazhan', $raid->name);
        $this->assertSame('Normal', $raid->difficulty);
        $this->assertSame(10, $raid->max_players);
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateGameVersion(GameVersion $gameVersion, array $data): void
    {
        UpdateGameVersion::run($gameVersion, $data);
    }
}
