<?php

namespace App\Services\Attendance\Calculators;

use App\Services\Attendance\Aggregators\ReportsAggregator;
use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\PlayerAttendanceStats;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GuildAttendanceCalculator
{
    public function __construct(
        protected ReportsAggregator $reportsAggregator,
    ) {}

    /**
     * Calculate attendance statistics from attendance records.
     *
     * For each player, calculates their attendance percentage based on reports
     * since their first appearance. Players not present in a report are considered absent.
     * Presence values of 1 (present) and 2 (benched) both count as valid attendance.
     *
     * @param  iterable<GuildAttendance>  $attendance
     * @return Collection<int, PlayerAttendanceStats>
     */
    public function calculate(iterable $attendance): Collection
    {
        $records = $this->sortAttendanceData($attendance);

        if ($records->isEmpty()) {
            return collect();
        }

        // First pass: find each player's earliest attendance
        /** @var array<string, array{firstAttendance: Carbon}> $playerInfo */
        $playerInfo = [];

        foreach ($records as $record) {
            foreach ($record->players as $player) {
                if (! isset($playerInfo[$player->name])) {
                    $playerInfo[$player->name] = [
                        'firstAttendance' => $record->startTime,
                    ];
                }
            }
        }

        // Second pass: for each player, count reports since their first attendance
        $stats = [];

        foreach ($playerInfo as $playerName => $info) {
            $totalReports = 0;
            $reportsAttended = 0;

            foreach ($records as $record) {
                // Only count reports on or after the player's first attendance
                if ($record->startTime->lt($info['firstAttendance'])) {
                    continue;
                }

                $totalReports++;

                // Check if player attended this report (presence 1 or 2)
                foreach ($record->players as $player) {
                    if ($player->name === $playerName && in_array($player->presence, [1, 2], true)) {
                        $reportsAttended++;
                        break;
                    }
                }
            }

            $percentage = $totalReports > 0 ? ($reportsAttended / $totalReports) * 100 : 0.0;

            $stats[$playerName] = new PlayerAttendanceStats(
                name: $playerName,
                firstAttendance: $info['firstAttendance'],
                totalReports: $totalReports,
                reportsAttended: $reportsAttended,
                percentage: round($percentage, 2),
            );
        }

        return new Collection($stats)
            ->sortBy(fn (PlayerAttendanceStats $s) => $s->name)
            ->values();
    }

    /**
     * Calculate attendance across multiple sources and aggregate the results.
     *
     * @param  array<iterable<GuildAttendance>>  $attendanceSets  One iterable per source
     * @return Collection<int, PlayerAttendanceStats>
     */
    public function calculateAndAggregate(array $attendanceSets): Collection
    {
        if (empty($attendanceSets)) {
            return collect();
        }

        $statsSets = collect();

        foreach ($attendanceSets as $attendance) {
            $stats = $this->calculate($attendance);
            $statsSets->push($stats);
        }

        return $this->reportsAggregator->aggregate($statsSets);
    }

    /**
     * Get the attendance records sorted by date ascending.
     *
     * @param  iterable<GuildAttendance>  $attendance
     * @return Collection<int, GuildAttendance>
     */
    protected function sortAttendanceData(iterable $attendance): Collection
    {
        return collect($attendance)->sortBy(fn (GuildAttendance $a) => $a->startTime)->values();
    }
}
