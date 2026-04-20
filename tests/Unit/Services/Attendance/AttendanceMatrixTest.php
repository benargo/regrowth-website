<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Services\Attendance\AttendanceMatrix;
use App\Services\Attendance\CharacterAttendanceRow;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonSerializable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function makeRow(Character $character, array $plannedAbsences = [null, null]): CharacterAttendanceRow
    {
        return new CharacterAttendanceRow(
            character: $character,
            percentage: 75.0,
            attendance: [1, 0],
            plannedAbsences: $plannedAbsences,
        );
    }

    #[Test]
    public function it_implements_arrayable_and_json_serializable(): void
    {
        $matrix = new AttendanceMatrix(collect(), collect());

        $this->assertInstanceOf(Arrayable::class, $matrix);
        $this->assertInstanceOf(JsonSerializable::class, $matrix);
    }

    #[Test]
    public function to_array_returns_empty_raids_rows_and_planned_absences_for_empty_matrix(): void
    {
        $matrix = new AttendanceMatrix(collect(), collect());

        $array = $matrix->toArray();

        $this->assertSame([], $array['raids']);
        $this->assertSame([], $array['rows']);
        $this->assertSame([], $array['planned_absences']);
    }

    #[Test]
    public function to_array_includes_all_raid_metadata(): void
    {
        $raids = collect([
            ['id' => 'r1', 'code' => 'ABC', 'dayOfWeek' => 'Wed', 'date' => '01/01', 'zoneName' => 'Karazhan'],
        ]);

        $matrix = new AttendanceMatrix($raids, collect());

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

        $matrix = new AttendanceMatrix(collect(), collect([$this->makeRow($character)]));

        $array = $matrix->toArray();

        $this->assertCount(1, $array['rows']);
        $this->assertSame('Thrall', $array['rows'][0]['name']);
        $this->assertSame(75.0, $array['rows'][0]['percentage']);
    }

    #[Test]
    public function to_array_collects_unique_planned_absences_into_the_top_level_map(): void
    {
        $rank = GuildRank::factory()->create();
        $charA = Character::factory()->create(['name' => 'Alice', 'rank_id' => $rank->id]);
        $charB = Character::factory()->create(['name' => 'Bob', 'rank_id' => $rank->id]);

        $absenceA = PlannedAbsence::factory()->create(['character_id' => $charA->id]);
        $absenceB = PlannedAbsence::factory()->create(['character_id' => $charB->id]);

        // Same absence referenced twice across a single row should appear once in the map
        $rows = collect([
            $this->makeRow($charA, [$absenceA, $absenceA]),
            $this->makeRow($charB, [$absenceB, null]),
        ]);

        $matrix = new AttendanceMatrix(collect(), $rows);

        $array = $matrix->toArray();

        $this->assertCount(2, $array['planned_absences']);
        $ids = array_column($array['planned_absences'], 'id');
        $this->assertContains($absenceA->id, $ids);
        $this->assertContains($absenceB->id, $ids);
    }

    #[Test]
    public function to_array_omits_planned_absences_when_no_row_references_any(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['rank_id' => $rank->id]);

        $matrix = new AttendanceMatrix(collect(), collect([$this->makeRow($character)]));

        $this->assertSame([], $matrix->toArray()['planned_absences']);
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $matrix = new AttendanceMatrix(collect(), collect());

        $this->assertSame($matrix->toArray(), $matrix->jsonSerialize());
    }
}
