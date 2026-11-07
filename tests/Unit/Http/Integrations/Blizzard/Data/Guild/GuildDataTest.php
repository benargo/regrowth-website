<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Guild;

use App\Http\Integrations\Blizzard\Data\Guild\GuildData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
class GuildDataTest extends TestCase
{
    #[Test]
    public function it_casts_a_guild_and_maps_the_snake_case_member_count(): void
    {
        $dto = GuildData::from([
            'id' => 4761007,
            'name' => 'Regrowth',
            'faction' => ['type' => 'ALLIANCE', 'name' => 'Alliance'],
            'member_count' => 623,
        ]);

        $this->assertSame(4761007, $dto->id);
        $this->assertSame('Regrowth', $dto->name);
        $this->assertSame(623, $dto->memberCount);
    }
}
