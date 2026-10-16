<?php

namespace App\Actions\Attendance;

use App\Data\Attendance\CharacterAttendanceRowData;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Finds characters whose attendance over their newest four raids is at
 * least 25 points below their overall attendance, among those at 25% or more.
 */
class FindDroppingOffCharacters
{
    use AsAction;

    /**
     * @param  Collection<int, CharacterAttendanceRowData>  $rows
     * @return Collection<int, CharacterAttendanceRowData>
     */
    public function handle(Collection $rows): Collection
    {
        return $rows
            ->filter(function (CharacterAttendanceRowData $row): bool {
                $recentRate = $row->recentAttendanceRate();

                return $recentRate !== null && $row->percentage >= 25 && $row->percentage - $recentRate >= 25;
            })
            ->values();
    }
}
