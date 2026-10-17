<?php

namespace App\Actions\Attendance;

use App\Data\Attendance\CharacterAttendanceData;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Finds characters whose attendance over their newest four raids is at
 * least 25 points above their overall attendance.
 */
class FindPickingUpCharacters
{
    use AsAction;

    /**
     * @param  Collection<int, CharacterAttendanceData>  $rows
     * @return Collection<int, CharacterAttendanceData>
     */
    public function handle(Collection $rows): Collection
    {
        return $rows
            ->filter(function (CharacterAttendanceData $row): bool {
                $recentRate = $row->recentAttendanceRate();

                return $recentRate !== null && $recentRate - $row->percentage >= 25;
            })
            ->values();
    }
}
