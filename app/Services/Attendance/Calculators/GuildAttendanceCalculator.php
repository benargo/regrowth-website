<?php

namespace App\Services\Attendance\Calculators;

use App\Services\Attendance\Aggregators\ReportsAggregator;
use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\PlayerAttendance;
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
        $records = $this->mergeByRaidDay($records);

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
     * Merge attendance records that fall on the same raid day into a single record.
     *
     * A "raid day" runs from 05:00 to 04:59 the following morning in the app timezone.
     * When multiple raids occur on the same raid day, players are considered present
     * if they appeared in any of the merged raids, using their best presence value.
     *
     * @param  Collection<int, GuildAttendance>  $records  Sorted by startTime ascending.
     * @return Collection<int, GuildAttendance>
     */
    protected function mergeByRaidDay(Collection $records): Collection
    {
        $timezone = config('app.timezone');

        return $records
            ->groupBy(fn (GuildAttendance $record) => $record->startTime->copy()->setTimezone($timezone)->subHours(5)->toDateString())
            ->map(function (Collection $group) {
                if ($group->count() === 1) {
                    return $group->first();
                }

                /** @var array<string, int> $mergedPlayers */
                $mergedPlayers = [];

                foreach ($group as $record) {
                    foreach ($record->players as $player) {
                        $current = $mergedPlayers[$player->name] ?? null;

                        if ($current === null || $this->presencePriority($player->presence) > $this->presencePriority($current)) {
                            $mergedPlayers[$player->name] = $player->presence;
                        }
                    }
                }

                $players = array_map(
                    fn (string $name, int $presence) => new PlayerAttendance(name: $name, presence: $presence),
                    array_keys($mergedPlayers),
                    array_values($mergedPlayers),
                );

                return new GuildAttendance(
                    code: $group->pluck('code')->implode('+'),
                    players: $players,
                    startTime: $group->first()->startTime,
                    zone: $group->first()->zone,
                );
            })
            ->values();
    }

    /**
     * Get the priority rank for a presence value (higher = better).
     */
    protected function presencePriority(int $presence): int
    {
        return match ($presence) {
            1 => 2,
            2 => 1,
            default => 0,
        };
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
