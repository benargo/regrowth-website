<?php

namespace App\Http\Controllers;

use App\Http\Resources\Raid\AttendanceScatterPointCollection;
use App\Services\Attendance\Calculator;
use App\Services\Attendance\CharacterAttendanceRowData;
use App\Services\Attendance\DataTable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceGraphsController extends Controller
{
    public function __construct(
        private readonly Calculator $calculator,
        private DataTable $table,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Raiding/Attendance/Graphs', [
            'scatterPoints' => Inertia::defer(
                fn () => $this->scatterPoints()->toResponse($request)->getData(true)['data'],
            ),
        ]);
    }

    /**
     * Build the per-character scatter-graph payload for the attendance dashboard.
     */
    private function scatterPoints(): AttendanceScatterPointCollection
    {
        $points = $this->table->rows()
            ->map(fn (CharacterAttendanceRowData $row) => $this->buildPoint($row))
            ->values();

        return new AttendanceScatterPointCollection($points);
    }

    /**
     * Reduce a CharacterAttendanceRowData to the scatter-point shape.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     playable_class: mixed,
     *     percentage: float,
     *     raidsTotal: int,
     *     raidsAttended: int,
     *     benched: int,
     *     plannedAbsences: int,
     *     otherAbsences: int,
     * }
     */
    private function buildPoint(CharacterAttendanceRowData $row): array
    {
        $attendance = $row->attendance;

        return [
            'id' => $row->character->id,
            'name' => $row->character->name,
            'playable_class' => $row->character->playable_class,
            'percentage' => $row->percentage,
            'raidsTotal' => count(array_filter($attendance, fn ($v) => $v !== null)),
            'raidsAttended' => count(array_filter($attendance, fn ($v) => $v === 1)),
            'benched' => count(array_filter($attendance, fn ($v) => $v === 2)),
            'plannedAbsences' => count(array_filter($row->plannedAbsences, fn ($v) => $v !== null)),
            'otherAbsences' => count(array_filter($attendance, fn ($v) => $v === 0)),
        ];
    }
}
