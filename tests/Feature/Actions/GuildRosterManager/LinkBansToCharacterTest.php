<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\LinkBansToCharacter;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Ban;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class LinkBansToCharacterTest extends TestCase
{
    use RefreshDatabase;

    private const string GUID = 'Player-5826-0A1B2C3D';

    private GameVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->version = GameVersion::factory()->create();
    }

    #[Test]
    public function it_links_a_ban_to_the_matched_character_and_its_user(): void
    {
        $character = Character::factory()->forGameVersion($this->version)->forUser()->withGrmGuid(self::GUID)->create();
        $ban = Ban::factory()->create(['game_version_id' => $this->version->id, 'grm_guid' => self::GUID]);

        LinkBansToCharacter::run($character);

        $ban->refresh();
        $this->assertSame($character->id, $ban->character_id);
        $this->assertSame($character->user_id, $ban->user_id);
    }

    #[Test]
    public function it_fills_the_user_on_a_ban_already_linked_to_the_character(): void
    {
        $character = Character::factory()->forGameVersion($this->version)->forUser()->withGrmGuid(self::GUID)->create();
        $ban = Ban::factory()->create([
            'game_version_id' => $this->version->id,
            'grm_guid' => self::GUID,
            'character_id' => $character->id,
        ]);

        LinkBansToCharacter::run($character);

        $this->assertSame($character->user_id, $ban->refresh()->user_id);
    }

    #[Test]
    public function it_leaves_a_character_without_a_user_linked_with_a_null_user(): void
    {
        $character = Character::factory()->forGameVersion($this->version)->withGrmGuid(self::GUID)->create(['user_id' => null]);
        $ban = Ban::factory()->create(['game_version_id' => $this->version->id, 'grm_guid' => self::GUID]);

        LinkBansToCharacter::run($character);

        $this->assertSame($character->id, $ban->refresh()->character_id);
        $this->assertNull($ban->user_id);
    }

    #[Test]
    public function it_leaves_bans_in_other_game_versions_alone(): void
    {
        $character = Character::factory()->forGameVersion($this->version)->forUser()->withGrmGuid(self::GUID)->create();
        $ban = Ban::factory()->create(['game_version_id' => GameVersion::factory(), 'grm_guid' => self::GUID]);

        LinkBansToCharacter::run($character);

        $this->assertNull($ban->refresh()->character_id);
        $this->assertNull($ban->user_id);
    }

    #[Test]
    public function it_never_relinks_a_ban_owned_by_another_character(): void
    {
        $character = Character::factory()->forGameVersion($this->version)->forUser()->withGrmGuid(self::GUID)->create();
        $other = Character::factory()->forGameVersion($this->version)->forUser()->create(['game_version_id' => $this->version->id]);
        $ban = Ban::factory()->forCharacter($other)->create(['game_version_id' => $this->version->id, 'grm_guid' => self::GUID]);

        LinkBansToCharacter::run($character);

        $ban->refresh();
        $this->assertSame($other->id, $ban->character_id);
        $this->assertSame($other->user_id, $ban->user_id);
    }

    #[Test]
    public function it_does_nothing_for_a_character_without_a_guid(): void
    {
        $character = Character::factory()->forGameVersion($this->version)->create(['grm_guid' => null]);
        $ban = Ban::factory()->create(['game_version_id' => $this->version->id, 'grm_guid' => 'Player-1']);

        LinkBansToCharacter::run($character);

        $this->assertNull($ban->refresh()->character_id);
    }
}
