<?php

namespace App\Services\Attendance\Models;

use App\Services\WarcraftLogs\Data\PlayerAttendance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CharacterAttendance
{
    /**
     * An array of reports the player attended, each containing the report ID and date.
     *
     * @var array<int, PlayerAttendance>
     */
    protected array $reports = [];

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
     * @return array{name: string, firstAttendance: string, totalReports: int, reportsAttended: int, percentage: float, reports: array<int, PlayerAttendance>}
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

    /**
     * Add a report attendance record for the player.
     */
    public function addReport(PlayerAttendance $attendance): void
    {
        $this->reports[] = $attendance;
    }

    /**
     * Get the player's attendance records.
     *
     * @return array<int, PlayerAttendance>
     */
    public function getReports(): array
    {
        return $this->reports;
    }

    public function collectReports(): Collection
    {
        return collect($this->reports)
            ->sortByDesc(fn (PlayerAttendance $attendance) => $attendance->presence)
            ->values()
            ->all();
    }
}
