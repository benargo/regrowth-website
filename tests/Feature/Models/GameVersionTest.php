<?php

namespace Tests\Feature\Models;

use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Models\Boss;
use App\Models\GameVersion;
use App\Models\GuildTag;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class GameVersionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_via_the_factory(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $this->assertDatabaseHas('game_versions', [
            'id' => $gameVersion->id,
            'title' => $gameVersion->title,
        ]);
    }

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
}
