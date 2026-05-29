<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Guild;

use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterCharacterData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuildRosterCharacterDataTest extends TestCase
{
    #[Test]
    public function it_casts_a_guild_roster_character(): void
    {
        $dto = GuildRosterCharacterData::from([
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
        ]);

        $this->assertSame(54321, $dto->id);
        $this->assertSame('Sylvanas', $dto->name);
        $this->assertSame(60, $dto->level);
        $this->assertInstanceOf(LinkData::class, $dto->playableClass);
        $this->assertSame('Rogue', $dto->playableClass->name);
        $this->assertSame(4, $dto->playableClass->id);
        $this->assertInstanceOf(LinkData::class, $dto->playableRace);
        $this->assertSame('Undead', $dto->playableRace->name);
        $this->assertSame(5, $dto->playableRace->id);
        $this->assertInstanceOf(LinkData::class, $dto->realm);
        $this->assertSame('Silvermoon', $dto->realm->name);
        $this->assertSame(1605, $dto->realm->id);
    }
}
