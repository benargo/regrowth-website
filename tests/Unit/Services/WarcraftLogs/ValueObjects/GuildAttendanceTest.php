<?php

namespace Tests\Unit\Services\WarcraftLogs\ValueObjects;

use App\Services\WarcraftLogs\ValueObjects\GuildAttendance;
use App\Services\WarcraftLogs\ValueObjects\PlayerAttendance;
use App\Services\WarcraftLogs\ValueObjects\Zone;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuildAttendanceTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleData(): array
    {
        return [
            'code' => 'abc123',
            'startTime' => 1736971200000,
            'players' => [
                ['name' => 'Thrall', 'type' => 'Shaman', 'presence' => 1],
                ['name' => 'Jaina', 'type' => 'Mage', 'presence' => 2],
            ],
            'zone' => ['id' => 1047, 'name' => 'Karazhan'],
        ];
    }

    #[Test]
    public function from_array_parses_all_fields(): void
    {
        $attendance = GuildAttendance::fromArray($this->sampleData());

        $this->assertInstanceOf(Arrayable::class, $attendance);
        $this->assertSame('abc123', $attendance->code);
        $this->assertInstanceOf(Carbon::class, $attendance->startTime);
        $this->assertEquals(1736971200000, $attendance->startTime->valueOf());
        $this->assertCount(2, $attendance->players);
        $this->assertContainsOnlyInstancesOf(PlayerAttendance::class, $attendance->players);
        $this->assertSame('Thrall', $attendance->players[0]->name);
        $this->assertSame(1, $attendance->players[0]->presence);
        $this->assertInstanceOf(Zone::class, $attendance->zone);
        $this->assertSame(1047, $attendance->zone->id);
    }

    #[Test]
    public function from_array_parses_without_zone(): void
    {
        $data = $this->sampleData();
        unset($data['zone']);

        $attendance = GuildAttendance::fromArray($data);

        $this->assertNull($attendance->zone);
    }

    #[Test]
    public function from_array_handles_empty_players(): void
    {
        $data = $this->sampleData();
        $data['players'] = [];

        $attendance = GuildAttendance::fromArray($data);

        $this->assertSame([], $attendance->players);
    }

    #[Test]
    public function to_array_includes_zone_when_present(): void
    {
        $attendance = GuildAttendance::fromArray($this->sampleData());

        $array = $attendance->toArray();

        $this->assertSame('abc123', $array['code']);
        $this->assertSame(1736971200000.0, $array['startTime']);
        $this->assertCount(2, $array['players']);
        $this->assertSame(['id' => 1047, 'name' => 'Karazhan'], $array['zone']);
    }

    #[Test]
    public function to_array_omits_zone_when_absent(): void
    {
        $data = $this->sampleData();
        unset($data['zone']);

        $attendance = GuildAttendance::fromArray($data);
        $array = $attendance->toArray();

        $this->assertArrayNotHasKey('zone', $array);
    }

    #[Test]
    public function filter_players_returns_new_instance_with_matching_players(): void
    {
        $attendance = GuildAttendance::fromArray($this->sampleData());

        $filtered = $attendance->filterPlayers(['Thrall']);

        $this->assertInstanceOf(GuildAttendance::class, $filtered);
        $this->assertCount(1, $filtered->players);
        $this->assertSame('Thrall', $filtered->players[0]->name);
    }

    #[Test]
    public function filter_players_returns_empty_players_when_no_match(): void
    {
        $attendance = GuildAttendance::fromArray($this->sampleData());

        $filtered = $attendance->filterPlayers(['NonExistent']);

        $this->assertCount(0, $filtered->players);
    }

    #[Test]
    public function filter_players_preserves_code_and_start_time_and_zone(): void
    {
        $attendance = GuildAttendance::fromArray($this->sampleData());

        $filtered = $attendance->filterPlayers(['Thrall']);

        $this->assertSame('abc123', $filtered->code);
        $this->assertSame($attendance->startTime, $filtered->startTime);
        $this->assertSame($attendance->zone, $filtered->zone);
    }
}
