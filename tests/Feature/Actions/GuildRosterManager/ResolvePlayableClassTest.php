<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\ResolvePlayableClass;
use App\Models\GameVersion;
use App\Models\PlayableClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class ResolvePlayableClassTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_matches_upper_case_tokens(): void
    {
        $version = GameVersion::factory()->create();
        $mage = PlayableClass::factory()->create(['name' => 'Mage']);
        $version->playableClasses()->attach($mage);

        $this->assertTrue($mage->is(ResolvePlayableClass::run($version, 'MAGE')));
    }

    #[Test]
    public function it_matches_multi_word_classes(): void
    {
        $version = GameVersion::factory()->create();
        $deathKnight = PlayableClass::factory()->create(['name' => 'Death Knight']);
        $version->playableClasses()->attach($deathKnight);

        $this->assertTrue($deathKnight->is(ResolvePlayableClass::run($version, 'DEATHKNIGHT')));
    }

    #[Test]
    public function it_returns_null_for_an_unknown_token(): void
    {
        $version = GameVersion::factory()->create();
        $version->playableClasses()->attach(PlayableClass::factory()->create(['name' => 'Mage']));

        $this->assertNull(ResolvePlayableClass::run($version, 'NOPE'));
    }

    #[Test]
    public function it_ignores_classes_not_linked_to_the_version(): void
    {
        $version = GameVersion::factory()->create();
        PlayableClass::factory()->create(['name' => 'Mage']);

        $this->assertNull(ResolvePlayableClass::run($version, 'MAGE'));
    }
}
