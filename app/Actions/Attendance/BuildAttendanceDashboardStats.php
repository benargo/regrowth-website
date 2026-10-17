<?php

namespace App\Actions\Attendance;

use App\Data\Attendance\AttendanceDashboardStatsData;
use App\Data\Attendance\CharacterAttendanceData;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\Phase;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds a game version's attendance dashboard statistics: its mains banded
 * by attendance percentage, the characters dropping off and picking up, the
 * player totals, the current and previous phase attendance, the characters
 * benched in the last week and the upcoming absences.
 */
class BuildAttendanceDashboardStats
{
    use AsAction;

    public function handle(GameVersion $gameVersion): AttendanceDashboardStatsData
    {
        $buildMatrix = BuildAttendanceMatrix::make();
        $rows = $buildMatrix->handle($gameVersion)->rows;
        $phases = ListStartedPhases::run($gameVersion);
        $currentPhase = $phases->first();
        [$mainRows, $linkedRows] = $rows->partition(fn (CharacterAttendanceData $row): bool => $row->character->is_main);

        return new AttendanceDashboardStatsData(
            percentageGroups: $mainRows
                ->groupBy(fn (CharacterAttendanceData $row): string => $this->band($row->percentage))
                ->map($this->characters(...)),
            droppingOff: $this->characters(FindDroppingOffCharacters::run($rows)),
            pickingUp: $this->characters(FindPickingUpCharacters::run($rows)),
            totalPlayers: $rows->count(),
            totalMains: $mainRows->count(),
            totalLinkedCharacters: $linkedRows->count(),
            phaseAttendance: $this->phaseAttendance($buildMatrix, $gameVersion, $currentPhase),
            previousPhaseAttendance: $this->phaseAttendance($buildMatrix, $gameVersion, $phases->get(1), before: $currentPhase?->start_date),
            benchedLastWeek: ListBenchedCharacters::run($gameVersion, now()->subDays(7)),
            upcomingAbsences: ListUpcomingAbsences::run($gameVersion),
        );
    }

    /**
     * The attendance band of a percentage: ">=80", "50-80" or "<50".
     */
    private function band(float $percentage): string
    {
        return match (true) {
            $percentage >= 80 => '>=80',
            $percentage >= 50 => '50-80',
            default => '<50',
        };
    }

    /**
     * The rows' characters, each without relations except its playable class.
     *
     * @param  Collection<int, CharacterAttendanceData>  $rows
     * @return Collection<int, Character>
     */
    private function characters(Collection $rows): Collection
    {
        return $rows
            ->map(fn (CharacterAttendanceData $row): Character => $row->characterWithClassOnly())
            ->values();
    }

    /**
     * The average attendance from the day before the phase's start, optionally until a
     * date, or null when there is no phase.
     */
    private function phaseAttendance(BuildAttendanceMatrix $buildMatrix, GameVersion $gameVersion, ?Phase $phase, ?CarbonInterface $before = null): ?float
    {
        if ($phase === null) {
            return null;
        }

        return AverageAttendance::run(
            $buildMatrix->handle($gameVersion, since: $phase->start_date->subDay(), before: $before)->rows
        );
    }
}
