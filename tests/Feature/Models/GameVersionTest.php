<?php

namespace Tests\Feature\Models;

use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Models\GameVersion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon as SupportCarbon;
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
    public function it_casts_release_date_to_carbon(): void
    {
        $gameVersion = GameVersion::factory()->create([
            'release_date' => Carbon::create(2026, 2, 6, 0, 0, 0, config('app.timezone')),
        ]);

        $this->assertInstanceOf(SupportCarbon::class, $gameVersion->fresh()->release_date);
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
}
