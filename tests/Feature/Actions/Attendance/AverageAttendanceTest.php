<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\AverageAttendance;
use App\Data\Attendance\CharacterAttendanceData;
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
        $this->assertSame(66.7, AverageAttendance::run(collect([$this->row(1, 1), $this->row(1, 2), $this->row(1, 2)])));
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, Presence|null>  $attendance
     */
    private function row(int $reportsAttended, int $totalReports, array $attendance = []): CharacterAttendanceData
    {
        return new CharacterAttendanceData(
            character: Character::factory()->withoutParents()->make(),
            firstAttendance: Carbon::parse('2025-01-01 19:00', 'UTC'),
            totalReports: $totalReports,
            reportsAttended: $reportsAttended,
            attendance: $attendance,
            plannedAbsences: array_fill(0, count($attendance), null),
        );
    }
}
