<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterCharacterData;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterMemberData;
use App\Http\Integrations\Blizzard\Data\Shared\HrefData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use App\Http\Resources\GuildRosterMemberCollection;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlayableSpecialization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Uri;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class GuildRosterMemberCollectionTest extends TestCase
{
    use RefreshDatabase;

    #[Group('contract')]
    #[Test]
    public function it_returns_expected_keys(): void
    {
        $result = (new GuildRosterMemberCollection([$this->makeMember()]))->toArray(new Request);

        $this->assertArrayHasKey('character', $result[0]);
        $this->assertArrayHasKey('rank', $result[0]);
        $this->assertArrayHasKey('id', $result[0]['character']);
        $this->assertArrayHasKey('name', $result[0]['character']);
        $this->assertArrayHasKey('level', $result[0]['character']);
        $this->assertArrayHasKey('playable_class_id', $result[0]['character']);
        $this->assertArrayHasKey('playable_race_id', $result[0]['character']);
        $this->assertArrayHasKey('is_known', $result[0]['character']);
        $this->assertArrayHasKey('is_main', $result[0]['character']);
        $this->assertArrayHasKey('specializations', $result[0]['character']);
    }

    #[Group('contract')]
    #[Test]
    public function it_returns_correct_scalar_values(): void
    {
        GuildRank::factory()->create(['position' => 9, 'name' => 'Warden']);

        $result = (new GuildRosterMemberCollection([$this->makeMember(
            id: 52461508,
            name: 'Ozona',
            level: 60,
            classId: 8,
            raceId: 7,
            rank: 9,
        )]))->toArray(new Request);

        $this->assertSame(52461508, $result[0]['character']['id']);
        $this->assertSame('Ozona', $result[0]['character']['name']);
        $this->assertSame(60, $result[0]['character']['level']);
        $this->assertSame(8, $result[0]['character']['playable_class_id']);
        $this->assertSame(7, $result[0]['character']['playable_race_id']);
        $this->assertSame('Warden', $result[0]['rank']);
    }

    // ==================== rank resolution ====================

    #[Group('contract')]
    #[Test]
    public function it_returns_null_rank_name_when_no_matching_guild_rank_exists(): void
    {
        $result = (new GuildRosterMemberCollection([$this->makeMember(rank: 9)]))->toArray(new Request);

        $this->assertNull($result[0]['rank']);
    }

    // ==================== excluded keys ====================

    #[Group('contract')]
    #[Test]
    public function it_excludes_realm(): void
    {
        $result = (new GuildRosterMemberCollection([$this->makeMember()]))->toArray(new Request);

        $this->assertArrayNotHasKey('realm', $result[0]['character']);
    }

    #[Group('contract')]
    #[Test]
    public function it_excludes_nested_key_hrefs(): void
    {
        $result = (new GuildRosterMemberCollection([$this->makeMember()]))->toArray(new Request);

        $this->assertArrayNotHasKey('playable_class', $result[0]['character']);
        $this->assertArrayNotHasKey('playable_race', $result[0]['character']);
    }

    // ==================== is known and is main ====================

    #[Group('contract')]
    #[Test]
    public function it_returns_is_known_true_when_character_exists_in_database(): void
    {
        Character::factory()->create(['id' => 52461508]);

        $result = (new GuildRosterMemberCollection([$this->makeMember(id: 52461508)]))->toArray(new Request);

        $this->assertTrue($result[0]['character']['is_known']);
    }

    #[Group('contract')]
    #[Test]
    public function it_returns_is_main_true_when_character_is_flagged_as_main(): void
    {
        Character::factory()->create(['id' => 52461508, 'is_main' => true]);

        $result = (new GuildRosterMemberCollection([$this->makeMember(id: 52461508)]))->toArray(new Request);

        $this->assertTrue($result[0]['character']['is_main']);
    }

    #[Group('contract')]
    #[Test]
    public function it_returns_is_main_false_when_character_is_not_flagged_as_main(): void
    {
        Character::factory()->create(['id' => 52461508, 'is_main' => false]);

        $result = (new GuildRosterMemberCollection([$this->makeMember(id: 52461508)]))->toArray(new Request);

        $this->assertFalse($result[0]['character']['is_main']);
    }

    #[Group('contract')]
    #[Test]
    public function it_returns_is_main_false_for_unknown_character(): void
    {
        $result = (new GuildRosterMemberCollection([$this->makeMember(id: 99999999)]))->toArray(new Request);

        $this->assertFalse($result[0]['character']['is_main']);
    }

    #[Group('contract')]
    #[Test]
    public function it_returns_is_known_false_when_character_does_not_exist_in_database(): void
    {
        $result = (new GuildRosterMemberCollection([$this->makeMember(id: 99999999)]))->toArray(new Request);

        $this->assertFalse($result[0]['character']['is_known']);
    }

    // ==================== specializations ====================

    #[Group('contract')]
    #[Test]
    public function it_returns_empty_specializations_for_unknown_character(): void
    {
        $result = (new GuildRosterMemberCollection([$this->makeMember(id: 99999999)]))->toArray(new Request);

        $this->assertSame([], $result[0]['character']['specializations']);
    }

    #[Group('contract')]
    #[Test]
    public function it_returns_specializations_for_known_character(): void
    {
        $character = Character::factory()->create(['id' => 52461508]);
        $spec = PlayableSpecialization::factory()->create();
        $character->specializations()->attach($spec, ['is_raid_spec' => true]);

        $result = (new GuildRosterMemberCollection([$this->makeMember(id: 52461508)]))->toArray(new Request);

        $this->assertCount(1, $result[0]['character']['specializations']);
        $this->assertSame($spec->id, $result[0]['character']['specializations'][0]['id']);
        $this->assertSame($spec->name, $result[0]['character']['specializations'][0]['name']);
        $this->assertSame($spec->role->value, $result[0]['character']['specializations'][0]['role']);
        $this->assertTrue($result[0]['character']['specializations'][0]['is_raid_spec']);
    }

    #[Group('contract')]
    #[Test]
    public function it_returns_empty_specializations_for_known_character_with_no_specs(): void
    {
        Character::factory()->create(['id' => 52461508]);

        $result = (new GuildRosterMemberCollection([$this->makeMember(id: 52461508)]))->toArray(new Request);

        $this->assertSame([], $result[0]['character']['specializations']);
    }

    // ==================== sort order ====================

    #[Group('contract')]
    #[Test]
    public function it_sorts_by_rank_then_level_descending_then_name(): void
    {
        GuildRank::factory()->create(['position' => 1, 'name' => 'Officer']);
        GuildRank::factory()->create(['position' => 2, 'name' => 'Raider']);

        $members = [
            $this->makeMember(id: 1, name: 'Zara', level: 70, rank: 2),
            $this->makeMember(id: 2, name: 'Aaron', level: 70, rank: 1),
            $this->makeMember(id: 3, name: 'Mia', level: 80, rank: 2),
            $this->makeMember(id: 4, name: 'Bob', level: 70, rank: 1),
            $this->makeMember(id: 5, name: 'Carl', level: 60, rank: 2),
        ];

        $result = (new GuildRosterMemberCollection($members))->toArray(new Request);

        $this->assertSame([
            ['name' => 'Aaron', 'rank' => 'Officer'],
            ['name' => 'Bob', 'rank' => 'Officer'],
            ['name' => 'Mia', 'rank' => 'Raider'],
            ['name' => 'Zara', 'rank' => 'Raider'],
            ['name' => 'Carl', 'rank' => 'Raider'],
        ], array_map(fn ($r) => ['name' => $r['character']['name'], 'rank' => $r['rank']], $result));
    }

    // ==================== helpers ====================

    private function makeMember(
        int $id = 1,
        string $name = 'Thrall',
        int $level = 60,
        int $classId = 7,
        int $raceId = 2,
        int $rank = 3,
    ): GuildRosterMemberData {
        $href = new HrefData(Uri::of('https://example.test'));

        return new GuildRosterMemberData(
            character: new GuildRosterCharacterData(
                id: $id,
                name: $name,
                level: $level,
                playableClass: new LinkData(key: $href, name: 'Shaman', id: $classId),
                playableRace: new LinkData(key: $href, name: 'Orc', id: $raceId),
                realm: new LinkData(key: $href, name: 'Thunderstrike', id: 1),
            ),
            rank: $rank,
        );
    }
}
