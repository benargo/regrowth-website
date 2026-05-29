<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Guild;

use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterCharacterData;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterMemberData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuildRosterMemberDataTest extends TestCase
{
    #[Test]
    public function it_casts_a_guild_roster_member(): void
    {
        $dto = GuildRosterMemberData::from([
            'character' => [
                'id' => 54321,
                'name' => 'Sylvanas',
                'level' => 60,
                'playable_class' => [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/4'],
                    'name' => 'Rogue',
                    'id' => 4,
                ],
                'playable_race' => [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/5'],
                    'name' => 'Undead',
                    'id' => 5,
                ],
                'realm' => [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/realm/1605'],
                    'name' => 'Silvermoon',
                    'id' => 1605,
                ],
            ],
            'rank' => 0,
        ]);

        $this->assertInstanceOf(GuildRosterCharacterData::class, $dto->character);
        $this->assertSame(54321, $dto->character->id);
        $this->assertSame('Sylvanas', $dto->character->name);
        $this->assertSame(0, $dto->rank);
    }

    #[Test]
    public function it_casts_a_regular_member_rank(): void
    {
        $dto = GuildRosterMemberData::from([
            'character' => [
                'id' => 11111,
                'name' => 'Cairne',
                'level' => 60,
                'playable_class' => [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/2'],
                    'name' => 'Paladin',
                    'id' => 2,
                ],
                'playable_race' => [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/6'],
                    'name' => 'Tauren',
                    'id' => 6,
                ],
                'realm' => [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/realm/1605'],
                    'name' => 'Silvermoon',
                    'id' => 1605,
                ],
            ],
            'rank' => 5,
        ]);

        $this->assertSame(5, $dto->rank);
        $this->assertSame(11111, $dto->character->id);
    }
}
