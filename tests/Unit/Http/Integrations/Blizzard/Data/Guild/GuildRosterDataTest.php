<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Guild;

use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterCharacterData;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterData;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterMemberData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuildRosterDataTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function memberPayload(int $id, string $name, int $rank): array
    {
        return [
            'character' => [
                'id' => $id,
                'name' => $name,
                'level' => 60,
                'playable_class' => [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-class/7'],
                    'name' => 'Shaman',
                    'id' => 7,
                ],
                'playable_race' => [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/playable-race/2'],
                    'name' => 'Orc',
                    'id' => 2,
                ],
                'realm' => [
                    'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/realm/1605'],
                    'name' => 'Silvermoon',
                    'id' => 1605,
                ],
            ],
            'rank' => $rank,
        ];
    }

    #[Test]
    public function it_casts_a_roster_with_multiple_members(): void
    {
        $dto = GuildRosterData::from([
            'guild' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/guild/silvermoon/regrowth'],
                'name' => 'Regrowth',
                'id' => 1,
            ],
            'members' => [
                $this->memberPayload(1001, 'Thrall', 0),
                $this->memberPayload(1002, 'Cairne', 1),
            ],
        ]);

        $this->assertInstanceOf(LinkData::class, $dto->guild);
        $this->assertSame('Regrowth', $dto->guild->name);
        $this->assertSame(1, $dto->guild->id);
        $this->assertCount(2, $dto->members);
        $this->assertContainsOnlyInstancesOf(GuildRosterMemberData::class, $dto->members);
        $this->assertInstanceOf(GuildRosterCharacterData::class, $dto->members[0]->character);
        $this->assertSame('Thrall', $dto->members[0]->character->name);
        $this->assertSame(0, $dto->members[0]->rank);
        $this->assertSame('Cairne', $dto->members[1]->character->name);
        $this->assertSame(1, $dto->members[1]->rank);
    }

    #[Test]
    public function it_casts_an_empty_roster(): void
    {
        $dto = GuildRosterData::from([
            'guild' => [
                'key' => ['href' => 'https://eu.api.blizzard.com/data/wow/guild/silvermoon/regrowth'],
                'name' => 'Regrowth',
                'id' => 1,
            ],
            'members' => [],
        ]);

        $this->assertSame([], $dto->members);
    }
}
