<?php

namespace Tests\Unit\Data\Attendance;

use App\Data\Attendance\AttendanceDashboardStatsData;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\PlannedAbsenceResource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class AttendanceDashboardStatsDataTest extends TestCase
{
    #[Test]
    public function it_exposes_its_properties(): void
    {
        $percentageGroups = collect(['>=80' => CharacterResource::collection([])]);
        $droppingOff = CharacterResource::collection([]);
        $pickingUp = CharacterResource::collection([]);
        $benchedLastWeek = collect(['Untagged' => collect()]);
        $upcomingAbsences = PlannedAbsenceResource::collection([]);

        $stats = new AttendanceDashboardStatsData(
            percentageGroups: $percentageGroups,
            droppingOff: $droppingOff,
            pickingUp: $pickingUp,
            totalPlayers: 12,
            totalMains: 8,
            totalLinkedCharacters: 4,
            phaseAttendance: 75.5,
            previousPhaseAttendance: 62.25,
            benchedLastWeek: $benchedLastWeek,
            upcomingAbsences: $upcomingAbsences,
        );

        $this->assertSame($percentageGroups, $stats->percentageGroups);
        $this->assertSame($droppingOff, $stats->droppingOff);
        $this->assertSame($pickingUp, $stats->pickingUp);
        $this->assertSame(12, $stats->totalPlayers);
        $this->assertSame(8, $stats->totalMains);
        $this->assertSame(4, $stats->totalLinkedCharacters);
        $this->assertSame(75.5, $stats->phaseAttendance);
        $this->assertSame(62.25, $stats->previousPhaseAttendance);
        $this->assertSame($benchedLastWeek, $stats->benchedLastWeek);
        $this->assertSame($upcomingAbsences, $stats->upcomingAbsences);
    }

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

    // ==================== helpers ====================

    private function stats(?float $phaseAttendance = 90.0, ?float $previousPhaseAttendance = 80.0): AttendanceDashboardStatsData
    {
        return new AttendanceDashboardStatsData(
            percentageGroups: collect(),
            droppingOff: CharacterResource::collection([]),
            pickingUp: CharacterResource::collection([]),
            totalPlayers: 0,
            totalMains: 0,
            totalLinkedCharacters: 0,
            phaseAttendance: $phaseAttendance,
            previousPhaseAttendance: $previousPhaseAttendance,
            benchedLastWeek: collect(),
            upcomingAbsences: PlannedAbsenceResource::collection([]),
        );
    }
}
