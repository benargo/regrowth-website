<?php

namespace App\Data\Attendance;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * The attendance matrix: one column per raid and one row per character.
 */
final class AttendanceMatrixData extends Data
{
    /**
     * @param  Collection<int, array{id: string, code: string|null, dayOfWeek: string, date: string, zoneName: string|null}>  $columns  newest raid first
     * @param  Collection<int, CharacterAttendanceRowData>  $rows  sorted by character name
     */
    public function __construct(
        public readonly Collection $columns,
        public readonly Collection $rows,
    ) {}
}
