<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\MatchCharacter;
use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class MatchCharacterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_matches_by_guid_even_after_a_rename(): void
    {
        $version = GameVersion::factory()->withSurnames()->create();
        $character = Character::factory()->forGameVersion($version)->withSurname('Ashvale')->withGrmGuid('Player-1-AAAA')->create(['name' => 'Mira']);

        $this->assertTrue($character->is(MatchCharacter::run($version, 'Player-1-AAAA', 'Liané', 'Moonfall')));
    }

    #[Test]
    public function it_matches_by_full_name_when_no_guid_is_stored(): void
    {
        $version = GameVersion::factory()->withSurnames()->create();
        $character = Character::factory()->forGameVersion($version)->withSurname('Ashvale')->create(['name' => 'Mira']);

        $this->assertTrue($character->is(MatchCharacter::run($version, 'Player-1-AAAA', 'Mira', 'Ashvale')));
    }

    #[Test]
    public function it_never_matches_on_first_name_alone(): void
    {
        $version = GameVersion::factory()->withSurnames()->create();
        Character::factory()->forGameVersion($version)->withSurname('Ashdown')->create(['name' => 'Pan']);

        $this->assertNull(MatchCharacter::run($version, 'Player-1-AAAA', 'Pan', 'Bakewell'));
    }

    #[Test]
    public function it_ignores_the_surname_column_when_the_game_version_has_no_surnames(): void
    {
        $version = GameVersion::factory()->create();
        $character = Character::factory()->forGameVersion($version)->withSurname('Ashvale')->create(['name' => 'Mira']);

        $this->assertTrue($character->is(MatchCharacter::run($version, 'Player-1-AAAA', 'Mira', null)));
    }

    #[Test]
    public function it_ignores_characters_in_other_game_versions(): void
    {
        $version = GameVersion::factory()->create();
        $other = GameVersion::factory()->create();
        Character::factory()->forGameVersion($other)->withGrmGuid('Player-1-AAAA')->create(['name' => 'Brannoc']);

        $this->assertNull(MatchCharacter::run($version, 'Player-1-AAAA', 'Brannoc', null));
    }

    #[Test]
    public function it_does_not_take_a_name_match_owned_by_another_guid(): void
    {
        $version = GameVersion::factory()->create();
        Character::factory()->forGameVersion($version)->withGrmGuid('Player-2-BBBB')->create(['name' => 'Brannoc']);

        $this->assertNull(MatchCharacter::run($version, 'Player-1-AAAA', 'Brannoc', null));
    }
}
