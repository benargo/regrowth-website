<?php

namespace Tests\Unit\Data\GuildRosterManager;

use App\Data\GuildRosterManager\OpenSyncData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class OpenSyncDataTest extends TestCase
{
    #[Test]
    public function it_maps_camel_case_input_onto_the_properties(): void
    {
        $sync = OpenSyncData::from([
            'gameVersion' => 'anniversary',
            'guildKey' => 'Regrowth-Thunderstrike',
            'addonVersion' => '1.2.3',
            'baseRevision' => 7,
            'openTicket' => 'ticket',
            'fileHash' => str_repeat('a', 64),
        ]);

        $this->assertSame('anniversary', $sync->game_version);
        $this->assertSame('Regrowth-Thunderstrike', $sync->guild_key);
        $this->assertSame('1.2.3', $sync->addon_version);
        $this->assertSame(7, $sync->base_revision);
        $this->assertSame('ticket', $sync->open_ticket);
        $this->assertSame(str_repeat('a', 64), $sync->file_hash);
    }

    #[Test]
    public function it_accepts_a_null_open_ticket(): void
    {
        $sync = OpenSyncData::from([
            'gameVersion' => 'anniversary',
            'guildKey' => 'Regrowth-Thunderstrike',
            'addonVersion' => '1.2.3',
            'baseRevision' => 0,
            'openTicket' => null,
            'fileHash' => str_repeat('a', 64),
        ]);

        $this->assertNull($sync->open_ticket);
    }

    #[Test]
    public function it_serialises_to_camel_case_keys(): void
    {
        $sync = new OpenSyncData(
            game_version: 'anniversary',
            guild_key: 'Regrowth-Thunderstrike',
            addon_version: '1.2.3',
            base_revision: 7,
            open_ticket: 'ticket',
            file_hash: str_repeat('a', 64),
        );

        $this->assertSame([
            'gameVersion' => 'anniversary',
            'guildKey' => 'Regrowth-Thunderstrike',
            'addonVersion' => '1.2.3',
            'baseRevision' => 7,
            'openTicket' => 'ticket',
            'fileHash' => str_repeat('a', 64),
        ], $sync->toArray());
    }
}
