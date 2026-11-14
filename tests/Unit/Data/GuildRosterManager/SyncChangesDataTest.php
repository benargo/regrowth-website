<?php

namespace Tests\Unit\Data\GuildRosterManager;

use App\Data\GuildRosterManager\SyncChangesData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class SyncChangesDataTest extends TestCase
{
    private const string BAN_ID = '0196a4f2-1c3d-7e8f-9a0b-1c2d3e4f5a6b';

    #[Test]
    public function it_maps_camel_case_input_onto_the_properties(): void
    {
        $changes = SyncChangesData::from($this->camelCasePayload());

        $this->assertSame(['Player-1-0A'], $changes->members);
        $this->assertSame(['Player-1-0B'], $changes->leavers);
        $this->assertSame(['12', '13'], $changes->alt_groups);
        $this->assertSame(['Player-1-0C'], $changes->departed_members);
        $this->assertSame(['12' => $this->previousPayload(), '13' => null], $changes->previous_alt_groups);
        $this->assertSame([self::BAN_ID], $changes->ban_ids);
    }

    #[Test]
    public function it_serialises_to_camel_case_keys_keeping_a_null_previous_payload_for_a_new_alt_group(): void
    {
        $changes = new SyncChangesData(
            members: ['Player-1-0A'],
            leavers: ['Player-1-0B'],
            alt_groups: ['12', '13'],
            departed_members: ['Player-1-0C'],
            previous_alt_groups: ['12' => $this->previousPayload(), '13' => null],
            ban_ids: [self::BAN_ID],
        );

        $this->assertSame($this->camelCasePayload(), $changes->toArray());
    }

    // ==================== helpers ====================

    /**
     * @return array<string, mixed>
     */
    private function camelCasePayload(): array
    {
        return [
            'members' => ['Player-1-0A'],
            'leavers' => ['Player-1-0B'],
            'altGroups' => ['12', '13'],
            'departedMembers' => ['Player-1-0C'],
            'previousAltGroups' => ['12' => $this->previousPayload(), '13' => null],
            'banIds' => [self::BAN_ID],
        ];
    }

    /**
     * @return array{id: string, main: string, members: list<string>}
     */
    private function previousPayload(): array
    {
        return ['id' => '12', 'main' => 'Mira Ashvale', 'members' => ['Player-1-0A']];
    }
}
