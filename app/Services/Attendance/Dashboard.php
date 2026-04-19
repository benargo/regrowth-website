<?php

namespace App\Services\Attendance;

use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use App\Models\TBC\Phase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class Dashboard
{
    public function __construct(
        private readonly Calculator $calculator,
        private DataTable $table,
    ) {}

    /**
     * The date of the most recent report, formatted for display.
     */
    public function latestReportDate(): ?string
    {
        $raw = Cache::tags(['attendance', 'reports'])->remember(
            'reports:latest_date',
            now()->addDay(),
            fn () => Report::max('start_time'),
        );

        return $raw ? Carbon::parse($raw)->format('d M Y') : null;
    }

    /**
     * Build the full stats payload for the attendance dashboard.
     *
     * @return array{above80: array<int, array{name: string, playable_class: array|null}>, between50and80: array<int, array{name: string, playable_class: array|null}>, droppingOff: array<int, array{name: string, playable_class: array|null}>, pickingUp: array<int, array{name: string, playable_class: array|null}>, totalPlayers: int, phaseAttendance: float|null, previousPhaseAttendance: float|null, benchedLastWeek: array<string, array<int, array{name: string, playable_class: mixed}>>}
     */
    public function stats(): array
    {
        $rows = $this->table->rows();

        $above80 = $rows
            ->filter(fn (CharacterAttendanceRow $r) => $r->percentage >= 80)
            ->map(fn (CharacterAttendanceRow $r) => $this->toPlayer($r))
            ->values()
            ->all();

        $between50and80 = $rows
            ->filter(fn (CharacterAttendanceRow $r) => $r->percentage >= 50 && $r->percentage < 80)
            ->map(fn (CharacterAttendanceRow $r) => $this->toPlayer($r))
            ->values()
            ->all();

        $phases = $this->phases();
        $currentPhase = $phases->first();
        $previousPhase = $phases->get(1);

        $phaseAttendance = $currentPhase
            ? $this->averageAttendanceForRange($currentPhase->start_date, null)
            : null;

        $previousPhaseAttendance = $previousPhase
            ? $this->averageAttendanceForRange($previousPhase->start_date, $currentPhase->start_date)
            : null;

        $droppingOff = collect($this->calculator->findDroppingOff($rows->all()))
            ->map(fn (CharacterAttendanceRow $r) => $this->toPlayer($r))
            ->values()
            ->all();

        $pickingUp = collect($this->calculator->findPickingUp($rows->all()))
            ->map(fn (CharacterAttendanceRow $r) => $this->toPlayer($r))
            ->values()
            ->all();

        return [
            'above80' => $above80,
            'between50and80' => $between50and80,
            'droppingOff' => $droppingOff,
            'pickingUp' => $pickingUp,
            'totalPlayers' => $rows->count(),
            'phaseAttendance' => $phaseAttendance,
            'previousPhaseAttendance' => $previousPhaseAttendance,
            'benchedLastWeek' => $this->calculator->benchedLastWeek(),
        ];
    }

    /**
     * Average the per-character attendance percentages for the given date range.
     */
    private function averageAttendanceForRange(?Carbon $since, ?Carbon $before): ?float
    {
        $rows = (new DataTable(
            $this->calculator,
            new Filters(sinceDate: $since, beforeDate: $before),
        ))->rows()->all();

        return $this->calculator->averageAttendance($rows);
    }

    /**
     * Started phases, ordered newest first. Cached to midnight — new phases going live today become visible the next day.
     *
     * @return Collection<int, Phase>
     */
    private function phases(): Collection
    {
        return Phase::hydrate(
            Cache::tags(['attendance'])->remember(
                'phases:where_started',
                now()->endOfDay(),
                function () {
                    return Phase::query()
                        ->whereNotNull('start_date')
                        ->where('start_date', '<=', now())
                        ->orderByDesc('start_date')
                        ->get()
                        ->toArray();
                }
            )
        );
    }

    /**
     * The next four planned absences starting today or later, with the `character` relation eager-loaded.
     *
     * @return Collection<int, PlannedAbsence>
     */
    public function upcomingAbsences(): Collection
    {
        return PlannedAbsence::query()
            ->with('character:id,name,playable_class')
            ->where('start_date', '>=', now()->startOfDay())
            ->orderBy('start_date')
            ->limit(4)
            ->get();
    }

    /**
     * Shape a matrix row down to the minimal player payload the dashboard cards render.
     *
     * @return array{name: string, playable_class: mixed}
     */
    private function toPlayer(CharacterAttendanceRow $row): array
    {
        return [
            'name' => $row->character->name,
            'playable_class' => $row->character->playable_class,
        ];
    }
}
