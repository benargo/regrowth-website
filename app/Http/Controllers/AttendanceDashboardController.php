<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\AverageAttendance;
use App\Actions\Attendance\BuildAttendanceMatrix;
use App\Actions\Attendance\FindDroppingOffCharacters;
use App\Actions\Attendance\FindPickingUpCharacters;
use App\Actions\Attendance\FindReportDateRange;
use App\Actions\Attendance\ListBenchedCharacters;
use App\Actions\Attendance\ListStartedPhases;
use App\Actions\Attendance\ListUpcomingAbsences;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Data\Attendance\AttendanceDashboardStatsData;
use App\Data\Attendance\BenchedCharacterData;
use App\Data\Attendance\CharacterAttendanceData;
use App\Http\Middleware\RemembersCurrentGameVersion;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\PlannedAbsenceResource;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\Phase;
use Carbon\CarbonInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

#[Middleware('auth')]
#[Middleware(RemembersCurrentGameVersion::class)]
#[Authorize('view-attendance')]
class AttendanceDashboardController extends Controller
{
    private ?GameVersion $gameVersion;

    /**
     * Display the attendance dashboard of the current game version.
     */
    public function __invoke(): Response
    {
        $gameVersions = GameVersion::withCountedAttendance()->get();
        $this->gameVersion = ResolveCurrentGameVersion::run($gameVersions);

        return Inertia::render('Raiding/Attendance/Dashboard', [
            'gameVersion' => $this->gameVersion === null ? null : new GameVersionResource($this->gameVersion),
            'gameVersions' => GameVersionResource::collection($gameVersions),
            'latestReportDate' => $this->gameVersion === null ? null : FindReportDateRange::run($this->gameVersion)->latest?->toDateString(),
            'stats' => Inertia::defer(fn (): AttendanceDashboardStatsData => $this->stats()),
        ]);
    }

    /**
     * Build the attendance statistics of the given game version.
     */
    private function stats(): AttendanceDashboardStatsData
    {
        $rows = $this->gameVersion === null ? collect() : BuildAttendanceMatrix::run($this->gameVersion)->rows;
        $phases = $this->gameVersion === null ? collect() : ListStartedPhases::run($this->gameVersion);
        $currentPhase = $phases->first();
        $previousPhase = $phases->get(1);
        [$mainRows, $linkedRows] = $rows->partition(fn (CharacterAttendanceData $row): bool => $row->character->is_main);

        return new AttendanceDashboardStatsData(
            percentageGroups: $mainRows
                ->groupBy(fn (CharacterAttendanceData $row): string => match (true) {
                    $row->percentage >= 80 => '>=80',
                    $row->percentage >= 50 => '50-80',
                    default => '<50',
                })
                ->map($this->toCharacterResources(...)),
            droppingOff: $this->toCharacterResources(FindDroppingOffCharacters::run($rows)),
            pickingUp: $this->toCharacterResources(FindPickingUpCharacters::run($rows)),
            totalPlayers: $rows->count(),
            totalMains: $mainRows->count(),
            totalLinkedCharacters: $linkedRows->count(),
            phaseAttendance: $this->phaseAttendance($currentPhase),
            previousPhaseAttendance: $this->phaseAttendance($previousPhase, before: $currentPhase?->start_date),
            benchedLastWeek: $this->benchedLastWeek(),
            upcomingAbsences: $this->upcomingAbsences(),
        );
    }

    /**
     * Calculate the average attendance from the given phase's start, optionally until a date, or null when there is no phase.
     */
    private function phaseAttendance(?Phase $phase, ?CarbonInterface $before = null): ?float
    {
        if ($phase === null) {
            return null;
        }

        return AverageAttendance::run(
            BuildAttendanceMatrix::run($this->gameVersion, since: $phase->start_date->subDay(), before: $before)->rows
        );
    }

    /**
     * Get the characters that have been benched in the last week, or an empty collection when no game version is active.
     *
     * @return Collection<string, Collection<int, BenchedCharacterData>> keyed by guild tag name
     */
    private function benchedLastWeek(): Collection
    {
        if ($this->gameVersion === null) {
            return collect();
        }

        return ListBenchedCharacters::run($this->gameVersion, now()->subDays(7));
    }

    /**
     * Get the upcoming absences of the current game version, or an empty collection when no game version is active.
     *
     * @return AnonymousResourceCollection<int, PlannedAbsenceResource>
     */
    private function upcomingAbsences(): AnonymousResourceCollection
    {
        if ($this->gameVersion === null) {
            return PlannedAbsenceResource::collection([]);
        }

        return PlannedAbsenceResource::collection(ListUpcomingAbsences::run($this->gameVersion));
    }

    /**
     * Convert attendance rows into a collection of character resources with only their playable classes.
     *
     * @param  Collection<int, CharacterAttendanceData>  $rows
     * @return AnonymousResourceCollection<int, CharacterResource>
     */
    private function toCharacterResources(Collection $rows): AnonymousResourceCollection
    {
        return CharacterResource::collection($rows
            ->map(fn (CharacterAttendanceData $row): Character => $row->characterWithClassOnly())
            ->values());
    }
}
