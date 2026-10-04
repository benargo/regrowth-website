<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Data\Attendance;

use App\Http\Integrations\WarcraftLogs\Data\Attendance\GuildAttendanceData;
use App\Http\Integrations\WarcraftLogs\Data\Attendance\PlayerAttendanceData;
use App\Http\Integrations\WarcraftLogs\Data\World\ZoneData;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class GuildAttendanceDataTest extends TestCase
{
    #[Test]
    public function it_hydrates_code_start_time_and_zone(): void
    {
        $attendance = GuildAttendanceData::from($this->sampleApiResponse());

        $this->assertSame('aBcD1234', $attendance->code);
        $this->assertInstanceOf(Carbon::class, $attendance->startTime);
        $this->assertSame(1700000000123, $attendance->startTime->getTimestampMs());
        $this->assertInstanceOf(ZoneData::class, $attendance->zone);
        $this->assertSame(1047, $attendance->zone->id);
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
    public function it_hydrates_an_empty_player_list_and_a_null_zone(): void
    {
        $attendance = GuildAttendanceData::from([...$this->sampleApiResponse(), 'players' => [], 'zone' => null]);

        $this->assertSame([], $attendance->players);
        $this->assertNull($attendance->zone);
    }

    #[Test]
    public function it_hydrates_a_missing_zone_as_null(): void
    {
        $payload = $this->sampleApiResponse();
        unset($payload['zone']);

        $attendance = GuildAttendanceData::from($payload);

        $this->assertNull($attendance->zone);
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

    #[Test]
    public function filter_players_keeps_only_the_named_players_in_order(): void
    {
        $filtered = GuildAttendanceData::from($this->sampleApiResponse())->filterPlayers(['Sylvanas', 'Thrall']);

        $this->assertSame(['Thrall', 'Sylvanas'], array_map(fn (PlayerAttendanceData $player): string => $player->name, $filtered->players));
        $this->assertSame([0, 1], array_keys($filtered->players));
    }

    #[Test]
    public function filter_players_returns_no_players_when_none_match(): void
    {
        $filtered = GuildAttendanceData::from($this->sampleApiResponse())->filterPlayers(['Arthas']);

        $this->assertSame([], $filtered->players);
    }

    #[Test]
    public function filter_players_returns_a_new_instance_with_the_other_fields_unchanged(): void
    {
        $original = GuildAttendanceData::from($this->sampleApiResponse());

        $filtered = $original->filterPlayers(['Thrall']);

        $this->assertNotSame($original, $filtered);
        $this->assertCount(3, $original->players);
        $this->assertSame($original->code, $filtered->code);
        $this->assertTrue($original->startTime->equalTo($filtered->startTime));
        $this->assertSame($original->zone, $filtered->zone);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            'code' => 'aBcD1234',
            'startTime' => 1700000000123.0,
            'players' => [
                ['name' => 'Thrall', 'presence' => 1],
                ['name' => 'Jaina', 'presence' => 2],
                ['name' => 'Sylvanas', 'presence' => 1],
            ],
            'zone' => [
                'id' => 1047,
                'name' => 'Karazhan',
                'difficulties' => [['id' => 3, 'name' => 'Normal', 'sizes' => [10]]],
                'expansion' => ['id' => 1001, 'name' => 'The Burning Crusade'],
            ],
        ];
    }
}
