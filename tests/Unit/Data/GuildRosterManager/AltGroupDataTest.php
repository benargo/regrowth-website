<?php

namespace Tests\Unit\Data\GuildRosterManager;

use App\Data\GuildRosterManager\AltGroupData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class AltGroupDataTest extends TestCase
{
    #[Test]
    public function it_maps_input_and_serialises_with_the_same_keys(): void
    {
        $group = AltGroupData::from($this->row());

        $this->assertSame($this->row(), $group->toArray());
    }

    #[Test]
    public function it_defaults_the_epoch_to_zero_when_absent(): void
    {
        $row = $this->row();
        unset($row['epoch']);

        $group = AltGroupData::from($row);

        $this->assertSame(0, $group->epoch);
    }

    #[Test]
    public function it_uses_the_group_id_as_its_key(): void
    {
        $group = AltGroupData::from($this->row());

        $this->assertSame('12', $group->key());
    }

    #[Test]
    public function it_builds_the_fingerprint_payload_without_the_epoch(): void
    {
        $group = AltGroupData::from($this->row());

        $this->assertSame([
            'id' => '12',
            'main' => 'Mira Ashvale',
            'members' => ['Player-5826-0A1B2C3D', 'Player-5826-0A1B2C3E'],
        ], $group->fingerprintPayload());
    }

    #[Test]
    public function it_keeps_a_null_main_in_the_fingerprint_payload(): void
    {
        $group = AltGroupData::from([...$this->row(), 'main' => null]);

        $this->assertArrayHasKey('main', $group->fingerprintPayload());
        $this->assertNull($group->fingerprintPayload()['main']);
    }

    // ==================== helpers ====================

    /**
     * @return array{id: string, main: string|null, members: array<int, string>, epoch: int}
     */
    private function row(): array
    {
        return [
            'id' => '12',
            'main' => 'Mira Ashvale',
            'members' => ['Player-5826-0A1B2C3D', 'Player-5826-0A1B2C3E'],
            'epoch' => 1785744000,
        ];
    }
}
