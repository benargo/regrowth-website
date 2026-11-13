<?php

namespace Tests\Unit\Data\GuildRosterManager;

use App\Data\GuildRosterManager\MemberData;
use App\Data\GuildRosterManager\MembershipEventData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class MemberDataTest extends TestCase
{
    #[Test]
    public function it_maps_camel_case_input_and_history_events(): void
    {
        $member = MemberData::from($this->row());

        $this->assertSame(3, $member->rank_index);
        $this->assertSame('12', $member->alt_group);
        $this->assertContainsOnlyInstancesOf(MembershipEventData::class, $member->history);
        $this->assertSame('joined', $member->history[0]->type);
        $this->assertSame('2026-08-02', $member->history[0]->date);
    }

    #[Test]
    public function it_serialises_with_camel_case_keys(): void
    {
        $member = MemberData::from($this->row());

        $this->assertSame($this->row(), $member->toArray());
    }

    #[Test]
    public function it_defaults_the_epoch_to_zero_when_absent(): void
    {
        $row = $this->row();
        unset($row['epoch']);

        $member = MemberData::from($row);

        $this->assertSame(0, $member->epoch);
    }

    #[Test]
    public function it_uses_the_character_guid_as_its_key(): void
    {
        $member = MemberData::from($this->row());

        $this->assertSame('Player-5826-0A1B2C3D', $member->key());
    }

    #[Test]
    public function it_builds_the_fingerprint_payload_without_the_epoch(): void
    {
        $member = MemberData::from($this->row());

        $this->assertSame([
            'altGroup' => '12',
            'class' => 'WARLOCK',
            'guid' => 'Player-5826-0A1B2C3D',
            'history' => [
                ['date' => '2026-08-02', 'type' => 'joined'],
            ],
            'level' => 60,
            'name' => 'Mira Ashvale',
            'race' => 'Scourge',
            'rankIndex' => 3,
        ], $member->fingerprintPayload());
    }

    #[Test]
    public function it_keeps_a_null_alt_group_in_the_fingerprint_payload(): void
    {
        $member = MemberData::from([...$this->row(), 'altGroup' => null]);

        $this->assertArrayHasKey('altGroup', $member->fingerprintPayload());
        $this->assertNull($member->fingerprintPayload()['altGroup']);
    }

    // ==================== helpers ====================

    /**
     * @return array{guid: string, name: string, rankIndex: int, level: int, class: string, race: string, altGroup: string|null, history: array<int, array{type: string, date: string}>, epoch: int}
     */
    private function row(): array
    {
        return [
            'guid' => 'Player-5826-0A1B2C3D',
            'name' => 'Mira Ashvale',
            'rankIndex' => 3,
            'level' => 60,
            'class' => 'WARLOCK',
            'race' => 'Scourge',
            'altGroup' => '12',
            'history' => [
                ['type' => 'joined', 'date' => '2026-08-02'],
            ],
            'epoch' => 1785657600,
        ];
    }
}
