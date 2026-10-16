<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\AverageAttendance;
use App\Data\Attendance\CharacterAttendanceRowData;
use App\Models\Character;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class AverageAttendanceTest extends TestCase
{
    #[Test]
    public function it_returns_null_without_rows(): void
    {
        $this->assertNull(AverageAttendance::run(collect()));
    }

    #[Test]
    public function it_returns_the_mean_percentage_to_one_decimal_place(): void
    {
        $this->assertSame(66.7, AverageAttendance::run(collect([$this->row(100.0), $this->row(50.0), $this->row(50.0)])));
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, Presence|null>  $attendance
     */
    private function row(float $percentage, array $attendance = []): CharacterAttendanceRowData
    {
        return new CharacterAttendanceRowData(
            character: Character::factory()->withoutParents()->make(),
            firstAttendance: Carbon::parse('2025-01-01 19:00', 'UTC'),
            totalReports: 0,
            reportsAttended: 0,
            percentage: $percentage,
            attendance: $attendance,
            plannedAbsences: array_fill(0, count($attendance), null),
        );
    }
}
