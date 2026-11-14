<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\ResolveBanCharacter;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class ResolveBanCharacterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_matches_a_two_word_forever_name(): void
    {
        $gameVersion = $this->foreverVersion();
        $thrall = $this->character('Thrall', 'Stormrage');

        $this->assertSame($thrall, ResolveBanCharacter::run($gameVersion, 'Thrall Stormrage', collect([$thrall])));
    }

    #[Test]
    public function it_matches_an_accented_name(): void
    {
        $gameVersion = $this->foreverVersion();
        $tears = $this->character('Teärs', 'Stormrage');

        $this->assertSame($tears, ResolveBanCharacter::run($gameVersion, 'Tears Stormrage', collect([$tears])));
    }

    #[Test]
    public function it_strips_the_realm_suffix_on_a_realm_version(): void
    {
        $gameVersion = GameVersion::factory()->create([
            'realm' => 'Thunderstrike',
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'uses_surnames' => false,
        ]);
        $brannoc = $this->character('Brannoc');

        $this->assertSame($brannoc, ResolveBanCharacter::run($gameVersion, 'Brannoc-Thunderstrike', collect([$brannoc])));
    }

    #[Test]
    public function it_returns_null_for_a_single_word_on_a_surname_version(): void
    {
        $gameVersion = $this->foreverVersion();
        $thrall = $this->character('Thrall', 'Stormrage');

        $this->assertNull(ResolveBanCharacter::run($gameVersion, 'Thrall', collect([$thrall])));
    }

    #[Test]
    public function it_returns_null_when_the_name_is_ambiguous(): void
    {
        $gameVersion = $this->foreverVersion();
        $candidates = collect([$this->character('Tears', 'Stormrage'), $this->character('Teärs', 'Stormrage')]);

        $this->assertNull(ResolveBanCharacter::run($gameVersion, 'TEARS STORMRAGE', $candidates));
    }

    // ==================== helpers ====================

    private function foreverVersion(): GameVersion
    {
        return GameVersion::factory()->withSurnames()->create([
            'blizzard_namespace' => BlizzardNamespace::FOREVER,
            'realm' => null,
        ]);
    }

    private function character(string $name, ?string $surname = null): Character
    {
        return Character::factory()->make(['name' => $name, 'surname' => $surname]);
    }
}
