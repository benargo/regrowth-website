<?php

namespace Tests\Unit\Data\GuildRosterManager;

use App\Data\GuildRosterManager\BanData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class BanDataTest extends TestCase
{
    #[Test]
    public function it_maps_camel_case_input_to_snake_case_properties(): void
    {
        $ban = BanData::from($this->row());

        $this->assertSame('2026-10-03T19:00:00Z', $ban->banned_at);
        $this->assertSame('Mira Ashvale', $ban->banned_by);
    }

    #[Test]
    public function it_serialises_with_camel_case_keys(): void
    {
        $ban = BanData::from($this->row());

        $this->assertSame($this->row(), $ban->toArray());
    }

    #[Test]
    public function it_defaults_the_epoch_to_zero_when_absent(): void
    {
        $row = $this->row();
        unset($row['epoch']);

        $ban = BanData::from($row);

        $this->assertSame(0, $ban->epoch);
    }

    #[Test]
    public function it_uses_the_character_guid_as_its_key(): void
    {
        $ban = BanData::from($this->row());

        $this->assertSame('Player-5826-0A1B2C42', $ban->key());
    }

    #[Test]
    public function it_builds_the_fingerprint_payload_without_the_epoch(): void
    {
        $ban = BanData::from($this->row());

        $this->assertSame([
            'bannedAt' => '2026-10-03T19:00:00Z',
            'bannedBy' => 'Mira Ashvale',
            'guid' => 'Player-5826-0A1B2C42',
            'name' => 'Bad Apple',
            'reason' => 'Ninja looting',
            'unbanned' => false,
        ], $ban->fingerprintPayload());
    }

    // ==================== helpers ====================

    /**
     * @return array{guid: string, name: string, unbanned: bool, bannedAt: string|null, bannedBy: string|null, reason: string|null, epoch: int}
     */
    private function row(): array
    {
        return [
            'guid' => 'Player-5826-0A1B2C42',
            'name' => 'Bad Apple',
            'unbanned' => false,
            'bannedAt' => '2026-10-03T19:00:00Z',
            'bannedBy' => 'Mira Ashvale',
            'reason' => 'Ninja looting',
            'epoch' => 1791054000,
        ];
    }
}
