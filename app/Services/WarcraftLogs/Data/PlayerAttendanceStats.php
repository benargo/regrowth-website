<?php

namespace App\Services\WarcraftLogs\Data;

use Carbon\Carbon;

readonly class PlayerAttendanceStats
{
    public function __construct(
        /**
         * The name of the player.
         */
        public string $name,

        /**
         * The date of the player's first attendance record.
         */
        public Carbon $firstAttendance,

        /**
         * The total number of reports since the player's first attendance.
         */
        public int $totalReports,

        /**
         * The number of reports the player attended.
         */
        public int $reportsAttended,

        /**
         * The player's attendance percentage (0-100).
         */
        public float $percentage,
    ) {}

    /**
     * @return array{name: string, firstAttendance: string, totalReports: int, reportsAttended: int, percentage: float}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'firstAttendance' => $this->firstAttendance->setTimezone(config('app.timezone'))->toIso8601String(),
            'totalReports' => $this->totalReports,
            'reportsAttended' => $this->reportsAttended,
            'percentage' => $this->percentage,
        ];
    }
}
