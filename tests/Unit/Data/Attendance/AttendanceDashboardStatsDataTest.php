<?php

namespace Tests\Unit\Data\Attendance;

use App\Data\Attendance\AttendanceDashboardStatsData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class AttendanceDashboardStatsDataTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_serializes_with_camel_case_keys(): void
    {
        $this->assertSame([
            'percentageGroups',
            'droppingOff',
            'pickingUp',
            'totalPlayers',
            'totalMains',
            'totalLinkedCharacters',
            'phaseAttendance',
            'previousPhaseAttendance',
            'benchedLastWeek',
            'upcomingAbsences',
        ], array_keys($this->stats()->toArray()));
    }

    #[Test]
    public function it_serializes_null_phase_attendances_as_null(): void
    {
        $serialized = $this->stats(phaseAttendance: null, previousPhaseAttendance: null)->toArray();

        $this->assertNull($serialized['phaseAttendance']);
        $this->assertNull($serialized['previousPhaseAttendance']);
    }

    #[Test]
    public function blank_serializes_zero_totals_null_phase_attendances_and_empty_lists(): void
    {
        $this->assertSame([
            'percentageGroups' => [],
            'droppingOff' => [],
            'pickingUp' => [],
            'totalPlayers' => 0,
            'totalMains' => 0,
            'totalLinkedCharacters' => 0,
            'phaseAttendance' => null,
            'previousPhaseAttendance' => null,
            'benchedLastWeek' => [],
            'upcomingAbsences' => [],
        ], AttendanceDashboardStatsData::blank()->toArray());
    }

    // ==================== helpers ====================

    private function stats(?float $phaseAttendance = 90.0, ?float $previousPhaseAttendance = 80.0): AttendanceDashboardStatsData
    {
        return new AttendanceDashboardStatsData(
            percentageGroups: collect(),
            droppingOff: collect(),
            pickingUp: collect(),
            totalPlayers: 0,
            totalMains: 0,
            totalLinkedCharacters: 0,
            phaseAttendance: $phaseAttendance,
            previousPhaseAttendance: $previousPhaseAttendance,
            benchedLastWeek: collect(),
            upcomingAbsences: collect(),
        );
    }
}
