<?php

namespace App\Services\Attendance;

use App\Models\Character;
use App\Models\PlannedAbsence;
use Spatie\LaravelData\Data;

final class CharacterAttendanceRowData extends Data
{
    public function __construct(
        public readonly Character $character,
        public readonly float $percentage,
        /** @var array<int, int|null> */
        public readonly array $attendance,
        /** @var array<int, PlannedAbsence|null> */
        public readonly array $plannedAbsences,
        /** @var array<int, array<int, string>>|null */
        public readonly ?array $attendanceNames = null,
    ) {}
}
