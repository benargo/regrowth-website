<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\NormaliseName;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class NormaliseNameTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_splits_a_forever_name(): void
    {
        $version = GameVersion::factory()->withSurnames()->create(['realm' => null]);

        $this->assertSame(['name' => 'Mira', 'surname' => 'Ashvale'], NormaliseName::run($version, 'Mira Ashvale'));
    }

    #[Test]
    public function it_keeps_accents(): void
    {
        $version = GameVersion::factory()->withSurnames()->create(['realm' => null]);

        $this->assertSame(['name' => 'Liané', 'surname' => 'Moonfall'], NormaliseName::run($version, 'Liané Moonfall'));
    }

    #[Test]
    public function it_strips_the_configured_realm(): void
    {
        $version = GameVersion::factory()->create(['realm' => 'Thunderstrike']);

        $this->assertSame(['name' => 'Brannoc', 'surname' => null], NormaliseName::run($version, 'Brannoc-Thunderstrike'));
    }

    #[Test]
    public function it_only_strips_the_configured_realm(): void
    {
        $version = GameVersion::factory()->create(['realm' => 'Thunderstrike']);

        $this->assertSame(['name' => 'Brannoc-Mograine', 'surname' => null], NormaliseName::run($version, 'Brannoc-Mograine'));
    }

    #[Test]
    public function it_strips_a_realm_written_without_spaces(): void
    {
        $version = GameVersion::factory()->create(['realm' => 'Living Flame']);

        $this->assertSame(['name' => 'Bob', 'surname' => null], NormaliseName::run($version, 'Bob-LivingFlame'));
    }
}
