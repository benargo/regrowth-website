<?php

namespace Tests\Feature\Models;

use App\Enums\Faction;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Models\Boss;
use App\Models\GameVersion;
use App\Models\GuildTag;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Raid;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class GameVersionTest extends TestCase
{
    use RefreshDatabase;

    // ==================== factory ====================

    #[Test]
    public function it_persists_via_the_factory(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $this->assertDatabaseHas('game_versions', [
            'id' => $gameVersion->id,
            'title' => $gameVersion->title,
        ]);
    }

    // ==================== enum casting ====================

    #[Test]
    public function it_casts_faction_to_the_faction_enum(): void
    {
        $gameVersion = GameVersion::factory()->create(['faction' => Faction::HORDE]);

        $this->assertSame(Faction::HORDE, $gameVersion->fresh()->faction);
    }

    #[Test]
    public function it_casts_blizzard_namespace_to_the_blizzard_namespace_enum(): void
    {
        $gameVersion = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::CLASSIC]);

        $this->assertSame(BlizzardNamespace::CLASSIC, $gameVersion->fresh()->blizzard_namespace);
    }

    #[Test]
    public function it_throws_when_reading_a_blizzard_namespace_value_outside_the_enum(): void
    {
        $gameVersion = GameVersion::factory()->create();

        DB::table('game_versions')
            ->where('id', $gameVersion->id)
            ->update(['blizzard_namespace' => 'some-future-namespace']);

        $this->expectException(\ValueError::class);

        $gameVersion->fresh()->blizzard_namespace;
    }

    #[Test]
    public function it_casts_theme_to_the_theme_enum(): void
    {
        $gameVersion = GameVersion::factory()->create(['theme' => Theme::FOREVER]);

        $this->assertSame(Theme::FOREVER, $gameVersion->fresh()->theme);
    }

    #[Test]
    public function it_throws_when_reading_a_theme_value_outside_the_enum(): void
    {
        $gameVersion = GameVersion::factory()->create();

        DB::table('game_versions')
            ->where('id', $gameVersion->id)
            ->update(['theme' => 'some-future-theme']);

        $this->expectException(\ValueError::class);

        $gameVersion->fresh()->theme;
    }

    #[Test]
    public function it_defaults_theme_to_the_default_theme_when_set_to_null(): void
    {
        $gameVersion = GameVersion::factory()->create(['theme' => null]);

        $this->assertSame(Theme::default(), $gameVersion->theme);
        $this->assertSame(Theme::default(), $gameVersion->fresh()->theme);

        $this->assertDatabaseHas('game_versions', [
            'id' => $gameVersion->id,
            'theme' => Theme::default()->value,
        ]);
    }

    // ==================== warcraft logs ids ====================

    #[Test]
    public function it_casts_warcraftlogs_ids_to_integers(): void
    {
        $gameVersion = GameVersion::factory()->create([
            'warcraftlogs_guild' => 774848,
            'warcraftlogs_expansion' => 1001,
        ]);

        $fresh = $gameVersion->fresh();

        $this->assertIsInt($fresh->warcraftlogs_guild);
        $this->assertIsInt($fresh->warcraftlogs_expansion);
    }

    // ==================== release date ====================

    #[Test]
    public function it_casts_release_date_to_carbon(): void
    {
        $gameVersion = GameVersion::factory()->create([
            'release_date' => Carbon::create(2026, 2, 6, 0, 0, 0, config('app.timezone')),
        ]);

        $this->assertInstanceOf(Carbon::class, $gameVersion->fresh()->release_date);
    }

    #[Test]
    public function it_round_trips_release_date_without_a_timezone_shift(): void
    {
        $releaseDate = Carbon::create(2026, 2, 6, 0, 0, 0, config('app.timezone'));

        $gameVersion = GameVersion::factory()->create(['release_date' => $releaseDate]);

        $this->assertTrue($releaseDate->eq($gameVersion->fresh()->release_date));
    }

    #[Test]
    public function it_converts_release_date_to_app_timezone_at_the_consumer_boundary(): void
    {
        $releaseDate = Carbon::create(2026, 2, 6, 0, 0, 0, config('app.timezone'));

        $gameVersion = GameVersion::factory()->create(['release_date' => $releaseDate]);

        $converted = $gameVersion->fresh()->release_date->copy()->setTimezone(config('app.timezone'));

        $this->assertSame('2026-02-06 00:00:00', $converted->toDateTimeString());
    }

    // ==================== nullable fields ====================

    #[Test]
    #[Group('edge-case')]
    public function it_allows_nullable_fields_to_be_null(): void
    {
        $gameVersion = GameVersion::factory()->create([
            'realm' => null,
            'faction' => null,
            'blizzard_namespace' => null,
            'warcraftlogs_guild' => null,
            'warcraftlogs_expansion' => null,
        ]);

        $fresh = $gameVersion->fresh();

        $this->assertNull($fresh->realm);
        $this->assertNull($fresh->faction);
        $this->assertNull($fresh->blizzard_namespace);
        $this->assertNull($fresh->warcraftlogs_guild);
        $this->assertNull($fresh->warcraftlogs_expansion);
    }

    // ==================== relationships ====================

    #[Test]
    public function it_has_many_bosses(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $boss = Boss::factory()->create(['game_version_id' => $gameVersion->id]);

        $this->assertTrue($gameVersion->bosses->contains($boss));
    }

    #[Test]
    public function it_has_many_phases(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->create(['game_version_id' => $gameVersion->id]);

        $this->assertTrue($gameVersion->phases->contains($phase));
    }

    #[Test]
    public function it_has_many_raids(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $raid = Raid::factory()->create(['game_version_id' => $gameVersion->id]);

        $this->assertTrue($gameVersion->raids->contains($raid));
    }

    #[Test]
    public function it_has_many_guild_tags(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $guildTag = GuildTag::factory()->create(['game_version_id' => $gameVersion->id]);

        $this->assertTrue($gameVersion->guildTags->contains($guildTag));
    }

    #[Test]
    public function it_has_many_zones(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $zone = Zone::factory()->create(['game_version_id' => $gameVersion->id]);

        $this->assertTrue($gameVersion->zones->contains($zone));
    }

    #[Test]
    public function it_belongs_to_many_playable_races(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $race = PlayableRace::factory()->create();

        $gameVersion->playableRaces()->attach($race);

        $this->assertTrue($gameVersion->fresh()->playableRaces->contains($race));
        $this->assertTrue($race->fresh()->gameVersions->contains($gameVersion));
    }

    #[Test]
    public function it_belongs_to_many_playable_classes(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $class = PlayableClass::factory()->create();

        $gameVersion->playableClasses()->attach($class);

        $this->assertTrue($gameVersion->fresh()->playableClasses->contains($class));
        $this->assertTrue($class->fresh()->gameVersions->contains($gameVersion));
    }
}
