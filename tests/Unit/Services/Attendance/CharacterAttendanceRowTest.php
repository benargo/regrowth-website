<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Services\Attendance\CharacterAttendanceRow;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonSerializable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterAttendanceRowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeRow(array $overrides = []): CharacterAttendanceRow
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);

        return new CharacterAttendanceRow(
            character: $overrides['character'] ?? $character,
            percentage: $overrides['percentage'] ?? 75.0,
            attendance: $overrides['attendance'] ?? [1, 0, 1],
            plannedAbsences: $overrides['plannedAbsences'] ?? [null, null, null],
            attendanceNames: $overrides['attendanceNames'] ?? null,
        );
    }

    #[Test]
    public function it_exposes_typed_properties(): void
    {
        $row = $this->makeRow();

        $this->assertInstanceOf(Character::class, $row->character);
        $this->assertSame(75.0, $row->percentage);
        $this->assertSame([1, 0, 1], $row->attendance);
        $this->assertSame([null, null, null], $row->plannedAbsences);
        $this->assertNull($row->attendanceNames);
    }

    #[Test]
    public function it_implements_arrayable_and_json_serializable(): void
    {
        $row = $this->makeRow();

        $this->assertInstanceOf(Arrayable::class, $row);
        $this->assertInstanceOf(JsonSerializable::class, $row);
    }

    #[Test]
    public function to_array_flattens_character_into_top_level_keys(): void
    {
        $row = $this->makeRow();

        $array = $row->toArray();

        $this->assertSame($row->character->id, $array['id']);
        $this->assertSame('Thrall', $array['name']);
        $this->assertSame($row->character->rank_id, $array['rank_id']);
        $this->assertArrayHasKey('playable_class', $array);
        $this->assertSame(75.0, $array['percentage']);
        $this->assertSame([1, 0, 1], $array['attendance']);
    }

    #[Test]
    public function to_array_maps_planned_absences_to_ids(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['rank_id' => $rank->id]);
        $absence = PlannedAbsence::factory()->create(['character_id' => $character->id]);

        $row = $this->makeRow([
            'character' => $character,
            'plannedAbsences' => [$absence, null],
        ]);

        $array = $row->toArray();

        $this->assertSame($absence->id, $array['planned_absences'][0]);
        $this->assertNull($array['planned_absences'][1]);
    }

    #[Test]
    public function to_array_omits_attendance_names_when_null(): void
    {
        $row = $this->makeRow(['attendanceNames' => null]);

        $this->assertArrayNotHasKey('attendance_names', $row->toArray());
    }

    #[Test]
    public function to_array_includes_attendance_names_when_provided(): void
    {
        $row = $this->makeRow([
            'attendanceNames' => [['Thrall'], []],
        ]);

        $this->assertSame([['Thrall'], []], $row->toArray()['attendance_names']);
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $row = $this->makeRow();

        $this->assertSame($row->toArray(), $row->jsonSerialize());
    }
}
