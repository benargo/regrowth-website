<?php

namespace Tests\Unit\Data\GuildRosterManager;

use App\Data\GuildRosterManager\LeaverData;
use App\Data\GuildRosterManager\MembershipEventData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class LeaverDataTest extends TestCase
{
    #[Test]
    public function it_maps_camel_case_input_and_history_events(): void
    {
        $leaver = LeaverData::from($this->row());

        $this->assertSame(5, $leaver->rank_index);
        $this->assertContainsOnlyInstancesOf(MembershipEventData::class, $leaver->history);
        $this->assertSame('left', $leaver->history[0]->type);
        $this->assertSame('2026-10-01', $leaver->history[0]->date);
    }

    #[Test]
    public function it_serialises_with_camel_case_keys(): void
    {
        $leaver = LeaverData::from($this->row());

        $this->assertSame($this->row(), $leaver->toArray());
    }

    #[Test]
    public function it_defaults_the_epoch_to_zero_when_absent(): void
    {
        $row = $this->row();
        unset($row['epoch']);

        $leaver = LeaverData::from($row);

        $this->assertSame(0, $leaver->epoch);
    }

    #[Test]
    public function it_uses_the_character_guid_as_its_key(): void
    {
        $leaver = LeaverData::from($this->row());

        $this->assertSame('Player-5826-0A1B2C40', $leaver->key());
    }

    #[Test]
    public function it_builds_the_fingerprint_payload_without_the_epoch_keeping_history_order(): void
    {
        $leaver = LeaverData::from($this->row());

        $this->assertSame([
            'class' => 'MAGE',
            'guid' => 'Player-5826-0A1B2C40',
            'history' => [
                ['date' => '2026-10-01', 'type' => 'left'],
                ['date' => '2026-08-10', 'type' => 'joined'],
            ],
            'level' => 41,
            'name' => 'Gone Fishing',
            'race' => 'Tauren',
            'rankIndex' => 5,
        ], $leaver->fingerprintPayload());
    }

    // ==================== helpers ====================

    /**
     * @return array{guid: string, name: string, rankIndex: int, level: int, class: string, race: string, history: array<int, array{type: string, date: string}>, epoch: int}
     */
    private function row(): array
    {
        return [
            'guid' => 'Player-5826-0A1B2C40',
            'name' => 'Gone Fishing',
            'rankIndex' => 5,
            'level' => 41,
            'class' => 'MAGE',
            'race' => 'Tauren',
            'history' => [
                ['type' => 'left', 'date' => '2026-10-01'],
                ['type' => 'joined', 'date' => '2026-08-10'],
            ],
            'epoch' => 1790856000,
        ];
    }
}
