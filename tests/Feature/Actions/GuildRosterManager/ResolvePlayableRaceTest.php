<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\ResolvePlayableRace;
use App\Models\GameVersion;
use App\Models\PlayableRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class ResolvePlayableRaceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_maps_scourge_to_undead(): void
    {
        $version = GameVersion::factory()->create();
        $undead = PlayableRace::factory()->create(['name' => 'Undead']);
        $version->playableRaces()->attach($undead);

        $this->assertTrue($undead->is(ResolvePlayableRace::run($version, 'Scourge')));
    }

    #[Test]
    public function it_matches_camel_case_tokens(): void
    {
        $version = GameVersion::factory()->create();
        $nightElf = PlayableRace::factory()->create(['name' => 'Night Elf']);
        $version->playableRaces()->attach($nightElf);

        $this->assertTrue($nightElf->is(ResolvePlayableRace::run($version, 'NightElf')));
    }

    #[Test]
    public function it_matches_forever_races(): void
    {
        $version = GameVersion::factory()->create();
        $skyborne = PlayableRace::factory()->create(['name' => 'Skyborne']);
        $version->playableRaces()->attach($skyborne);

        $this->assertTrue($skyborne->is(ResolvePlayableRace::run($version, 'Skyborne')));
    }

    #[Test]
    public function it_returns_null_for_an_unknown_token(): void
    {
        $version = GameVersion::factory()->create();
        $version->playableRaces()->attach(PlayableRace::factory()->create(['name' => 'Night Elf']));

        $this->assertNull(ResolvePlayableRace::run($version, 'Nope'));
    }
}
