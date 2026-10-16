<?php

namespace Tests\Unit\Data\Attendance;

use App\Data\Attendance\AttendanceMatrixData;
use App\Data\Attendance\CharacterAttendanceRowData;
use App\Models\Character;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class AttendanceMatrixDataTest extends TestCase
{
    #[Test]
    public function it_exposes_its_columns_and_rows(): void
    {
        $columns = collect([
            ['id' => 'r1', 'code' => 'ABC', 'dayOfWeek' => 'Wed', 'date' => '01/01', 'zoneName' => 'Karazhan'],
        ]);
        $rows = collect([
            new CharacterAttendanceRowData(
                character: Character::factory()->withoutParents()->make(['id' => 1]),
                firstAttendance: Carbon::parse('2025-01-01 19:00', 'UTC'),
                totalReports: 1,
                reportsAttended: 0,
                percentage: 0.0,
                attendance: [null],
                plannedAbsences: [null],
            ),
        ]);

        $matrix = new AttendanceMatrixData(columns: $columns, rows: $rows);

        $this->assertSame($columns, $matrix->columns);
        $this->assertSame($rows, $matrix->rows);
    }
}
