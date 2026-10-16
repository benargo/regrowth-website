<?php

namespace App\Actions\Attendance;

use App\Data\Attendance\CharacterAttendanceRowData;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Averages the attendance percentage of a set of rows.
 */
class AverageAttendance
{
    use AsAction;

    /**
     * @param  Collection<int, CharacterAttendanceRowData>  $rows
     * @return float|null The mean to one decimal place, or null when there are no rows.
     */
    public function handle(Collection $rows): ?float
    {
        return $rows->isEmpty() ? null : round($rows->avg('percentage'), 1);
    }
}
