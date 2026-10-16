<?php

namespace App\Data\Attendance;

use App\Enums\Presence;
use App\Models\Character;
use App\Models\PlannedAbsence;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * One character's attendance across the matrix's raids.
 */
final class CharacterAttendanceRowData extends Data
{
    /**
     * @param  array<int, Presence|null>  $attendance  newest raid first
     * @param  array<int, PlannedAbsence|null>  $plannedAbsences
     * @param  array<int, array<int, string>>|null  $attendanceNames
     */
    public function __construct(
        public readonly Character $character,
        public readonly CarbonInterface $firstAttendance,
        public readonly int $totalReports,
        public readonly int $reportsAttended,
        public readonly float $percentage,
        public readonly array $attendance,
        public readonly array $plannedAbsences,
        public readonly ?array $attendanceNames = null,
    ) {}

    /**
     * The attended share, as a percentage, of the newest four raids with a presence, or
     * null when fewer than three such raids exist.
     */
    public function recentAttendanceRate(): ?float
    {
        $recent = collect($this->attendance)->whereNotNull()->take(4);

        if ($recent->count() < 3) {
            return null;
        }

        return $recent->filter(fn (Presence $presence): bool => $presence->isAttended())->count() / $recent->count() * 100;
    }
}
