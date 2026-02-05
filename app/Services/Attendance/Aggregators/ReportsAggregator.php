<?php

namespace App\Services\Attendance\Aggregators;

use App\Services\WarcraftLogs\Data\PlayerAttendanceStats;
use Illuminate\Support\Collection;

class ReportsAggregator
{
    /**
     * Aggregate PlayerAttendanceStats from multiple sources.
     *
     * For each player:
     * - Keep the earliest `firstAttendance` across all sources
     * - Sum `totalReports` from all sources
     * - Sum `reportsAttended` from all sources
     * - Recalculate percentage from summed values
     *
     * @param  Collection<int, Collection<int, PlayerAttendanceStats>>  $statsSets
     * @return Collection<int, PlayerAttendanceStats>
     */
    public function aggregate(Collection $statsSets): Collection
    {
        /** @var array<string, array{firstAttendance: \Carbon\Carbon, totalReports: int, reportsAttended: int}> $aggregated */
        $aggregated = [];

        foreach ($statsSets as $statsCollection) {
            foreach ($statsCollection as $stats) {
                $name = $stats->name;

                if (! isset($aggregated[$name])) {
                    $aggregated[$name] = [
                        'firstAttendance' => $stats->firstAttendance,
                        'totalReports' => $stats->totalReports,
                        'reportsAttended' => $stats->reportsAttended,
                    ];
                } else {
                    // Keep earliest firstAttendance
                    if ($stats->firstAttendance->lt($aggregated[$name]['firstAttendance'])) {
                        $aggregated[$name]['firstAttendance'] = $stats->firstAttendance;
                    }
                    // Sum reports
                    $aggregated[$name]['totalReports'] += $stats->totalReports;
                    $aggregated[$name]['reportsAttended'] += $stats->reportsAttended;
                }
            }
        }

        // Convert to PlayerAttendanceStats objects
        $result = [];
        foreach ($aggregated as $name => $data) {
            $percentage = $data['totalReports'] > 0
                ? round(($data['reportsAttended'] / $data['totalReports']) * 100, 2)
                : 0.0;

            $result[$name] = new PlayerAttendanceStats(
                name: $name,
                firstAttendance: $data['firstAttendance'],
                totalReports: $data['totalReports'],
                reportsAttended: $data['reportsAttended'],
                percentage: $percentage,
            );
        }

        return collect($result)->sortBy(fn (PlayerAttendanceStats $s) => $s->name)->values();
    }
}
