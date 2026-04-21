<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Services\Attendance\AttendanceMatrixData;
use App\Services\Attendance\CharacterAttendanceRowData;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonSerializable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function makeRow(Character $character, array $plannedAbsences = [null, null]): CharacterAttendanceRowData
    {
        return new CharacterAttendanceRowData(
            character: $character,
            percentage: 75.0,
            attendance: [1, 0],
            plannedAbsences: $plannedAbsences,
        );
    }

    #[Test]
    public function it_implements_arrayable_and_json_serializable(): void
    {
        $matrix = new AttendanceMatrixData(collect(), collect());

        $this->assertInstanceOf(Arrayable::class, $matrix);
        $this->assertInstanceOf(JsonSerializable::class, $matrix);
    }

    #[Test]
    public function to_array_returns_empty_raids_and_rows_for_empty_matrix(): void
    {
        $matrix = new AttendanceMatrixData(collect(), collect());

        $array = $matrix->toArray();

        $this->assertSame([], $array['raids']);
        $this->assertSame([], $array['rows']);
        $this->assertArrayNotHasKey('planned_absences', $array);
    }

    #[Test]
    public function to_array_includes_all_raid_metadata(): void
    {
        $raids = collect([
            ['id' => 'r1', 'code' => 'ABC', 'dayOfWeek' => 'Wed', 'date' => '01/01', 'zoneName' => 'Karazhan'],
        ]);

        $matrix = new AttendanceMatrixData($raids, collect());

        $array = $matrix->toArray();

        $this->assertCount(1, $array['raids']);
        $this->assertSame('r1', $array['raids'][0]['id']);
        $this->assertSame('Karazhan', $array['raids'][0]['zoneName']);
    }

    #[Test]
    public function to_array_flattens_rows_to_the_row_dtos_toarray_shape(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);

        $matrix = new AttendanceMatrixData(collect(), collect([$this->makeRow($character)]));

        $array = $matrix->toArray();

        $this->assertCount(1, $array['rows']);
        $this->assertSame('Thrall', $array['rows'][0]['name']);
        $this->assertSame(75.0, $array['rows'][0]['percentage']);
    }

    #[Test]
    public function to_array_exposes_planned_absences_per_row_as_ids(): void
    {
        $rank = GuildRank::factory()->create();
        $charA = Character::factory()->create(['name' => 'Alice', 'rank_id' => $rank->id]);

        $absenceA = PlannedAbsence::factory()->create(['character_id' => $charA->id]);

        $rows = collect([$this->makeRow($charA, [$absenceA, null])]);

        $matrix = new AttendanceMatrixData(collect(), $rows);

        $array = $matrix->toArray();

        $this->assertSame($absenceA->id, $array['rows'][0]['planned_absences'][0]);
        $this->assertNull($array['rows'][0]['planned_absences'][1]);
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $matrix = new AttendanceMatrixData(collect(), collect());

        $this->assertSame($matrix->toArray(), $matrix->jsonSerialize());
    }
}
