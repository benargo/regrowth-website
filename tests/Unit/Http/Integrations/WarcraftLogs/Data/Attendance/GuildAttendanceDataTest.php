<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Data\Attendance;

use App\Http\Integrations\WarcraftLogs\Data\Attendance\GuildAttendanceData;
use App\Http\Integrations\WarcraftLogs\Data\Attendance\PlayerAttendanceData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class GuildAttendanceDataTest extends TestCase
{
    #[Test]
    public function it_hydrates_the_code(): void
    {
        $attendance = GuildAttendanceData::from($this->sampleApiResponse());

        $this->assertSame('aBcD1234', $attendance->code);
    }

    #[Test]
    public function it_hydrates_the_players_as_player_attendance_data(): void
    {
        $attendance = GuildAttendanceData::from($this->sampleApiResponse());

        $this->assertCount(3, $attendance->players);
        $this->assertContainsOnlyInstancesOf(PlayerAttendanceData::class, $attendance->players);
        $this->assertSame('Jaina', $attendance->players[1]->name);
        $this->assertSame(2, $attendance->players[1]->presence);
    }

    #[Test]
    public function it_hydrates_an_empty_player_list(): void
    {
        $attendance = GuildAttendanceData::from([...$this->sampleApiResponse(), 'players' => []]);

        $this->assertSame([], $attendance->players);
    }

    #[Test]
    public function it_hydrates_null_players_as_an_empty_list(): void
    {
        $attendance = GuildAttendanceData::from([...$this->sampleApiResponse(), 'players' => null]);

        $this->assertSame([], $attendance->players);
    }

    #[Test]
    public function it_hydrates_missing_players_as_an_empty_list(): void
    {
        $payload = $this->sampleApiResponse();
        unset($payload['players']);

        $attendance = GuildAttendanceData::from($payload);

        $this->assertSame([], $attendance->players);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            'code' => 'aBcD1234',
            'players' => [
                ['name' => 'Thrall', 'presence' => 1],
                ['name' => 'Jaina', 'presence' => 2],
                ['name' => 'Sylvanas', 'presence' => 1],
            ],
        ];
    }
}
