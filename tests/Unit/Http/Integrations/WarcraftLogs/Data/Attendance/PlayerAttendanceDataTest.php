<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Data\Attendance;

use App\Http\Integrations\WarcraftLogs\Data\Attendance\PlayerAttendanceData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class PlayerAttendanceDataTest extends TestCase
{
    #[Test]
    public function it_hydrates_name_and_presence(): void
    {
        $player = PlayerAttendanceData::from($this->sampleApiResponse());

        $this->assertSame('Thrall', $player->name);
        $this->assertSame(1, $player->presence);
    }

    #[Test]
    public function it_ignores_keys_it_does_not_model(): void
    {
        $player = PlayerAttendanceData::from([...$this->sampleApiResponse(), 'type' => 'Shaman']);

        $this->assertSame('Thrall', $player->name);
    }

    /**
     * @return array{name: string, presence: int}
     */
    private function sampleApiResponse(): array
    {
        return ['name' => 'Thrall', 'presence' => 1];
    }
}
