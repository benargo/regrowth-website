<?php

namespace App\Services\Attendance;

use App\Http\Resources\Raid\AttendanceScatterPointCollection;

class Graphs
{
    public function __construct(
        private readonly Calculator $calculator,
        private DataTable $table,
    ) {}

    /**
     * Build the per-character scatter-graph payload for the attendance dashboard.
     */
    public function scatterPoints(): AttendanceScatterPointCollection
    {
        $points = $this->table->rows()
            ->map(fn (CharacterAttendanceRow $row) => $this->buildPoint($row))
            ->values();

        return new AttendanceScatterPointCollection($points);
    }

    /**
     * Reduce a CharacterAttendanceRow to the scatter-point shape.
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
    private function buildPoint(CharacterAttendanceRow $row): array
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
