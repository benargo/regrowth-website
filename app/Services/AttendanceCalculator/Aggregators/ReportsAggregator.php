<?php

namespace App\Services\AttendanceCalculator\Aggregators;

use App\Services\AttendanceCalculator\CharacterAttendanceStats;
use Illuminate\Support\Collection;

class ReportsAggregator
{
    /**
     * Aggregate CharacterAttendanceStats from multiple sources.
     *
     * For each character:
     * - Keep the first `id` encountered (same character across all sources)
     * - Keep the earliest `firstAttendance` across all sources
     * - Sum `totalReports` from all sources
     * - Sum `reportsAttended` from all sources
     * - Recalculate percentage from summed values
     *
     * @param  Collection<int, Collection<int, CharacterAttendanceStats>>  $statsSets
     * @return Collection<int, CharacterAttendanceStats>
     */
    public static function aggregate(Collection $statsSets): Collection
    {
        /** @var array<string, array{id: int, firstAttendance: \Carbon\Carbon, totalReports: int, reportsAttended: int}> $aggregated */
        $aggregated = [];

        foreach ($statsSets as $statsCollection) {
            foreach ($statsCollection as $stats) {
                $name = $stats->name;

                if (! isset($aggregated[$name])) {
                    $aggregated[$name] = [
                        'id' => $stats->id,
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

        // Convert to CharacterAttendanceStats objects
        $result = [];
        foreach ($aggregated as $name => $data) {
            $percentage = $data['totalReports'] > 0
                ? round(($data['reportsAttended'] / $data['totalReports']) * 100, 2)
                : 0.0;

            $result[$name] = new CharacterAttendanceStats(
                id: $data['id'],
                name: $name,
                firstAttendance: $data['firstAttendance'],
                totalReports: $data['totalReports'],
                reportsAttended: $data['reportsAttended'],
                percentage: $percentage,
            );
        }

        return collect($result)->sortBy(fn (CharacterAttendanceStats $s) => $s->name)->values();
    }
}
