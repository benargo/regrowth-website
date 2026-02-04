<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Data\GuildAttendance;
use App\Services\WarcraftLogs\Data\PlayerAttendanceStats;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * The attendance data to calculate stats from.
     */
    protected iterable $attendance;

    /**
     * The guild tags to filter by when fetching attendance.
     *
     * @var array<int>
     */
    protected array $tags = [];

    /**
     * Optional start date filter.
     */
    protected ?Carbon $startDate = null;

    /**
     * Optional end date filter.
     */
    protected ?Carbon $endDate = null;

    /**
     * Optional player names filter.
     *
     * @var array<string>|null
     */
    protected ?array $playerNames = null;

    /**
     * Optional zone ID filter.
     */
    protected ?int $zoneID = null;

    public function __construct(
        protected GuildService $guildService,
        iterable $attendance = [],
    ) {
        $this->attendance = $attendance;
    }

    /**
     * Set the guild tags to filter by.
     *
     * @param  array<int>  $tags
     */
    public function tags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Set the start date filter.
     */
    public function startDate(?Carbon $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * Set the end date filter.
     */
    public function endDate(?Carbon $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    /**
     * Set the player names filter.
     *
     * @param  array<string>|null  $playerNames
     */
    public function playerNames(?array $playerNames): static
    {
        $this->playerNames = $playerNames;

        return $this;
    }

    /**
     * Set the zone ID filter.
     */
    public function zoneID(?int $zoneID): static
    {
        $this->zoneID = $zoneID;

        return $this;
    }

    /**
     * Get the raw attendance data, sorted by date ascending.
     */
    public function attendance(): ?Collection
    {
        return $this->sortAttendanceData($this->attendance);
    }

    /**
     * Calculate attendance statistics for each player.
     *
     * If tags are set, fetches attendance data for each tag separately and aggregates the results.
     * Otherwise, uses the attendance data passed to the constructor.
     *
     * For each player, calculates their attendance percentage based on reports
     * since their first appearance. Players not present in a report are considered absent.
     * Presence values of 1 (present) and 2 (benched) both count as valid attendance.
     *
     * @return Collection<int, PlayerAttendanceStats> Attendance stats for each player.
     */
    public function calculate(): Collection
    {
        // If tags are specified, fetch and calculate for each tag separately, then aggregate
        if (! empty($this->tags)) {
            return $this->calculateForTags();
        }

        return $this->calculateFromAttendance($this->attendance);
    }

    /**
     * Calculate attendance stats across multiple guild tags.
     *
     * @return Collection<int, PlayerAttendanceStats>
     */
    protected function calculateForTags(): Collection
    {
        if (empty($this->tags)) {
            return collect();
        }

        $statsSets = collect();

        foreach ($this->tags as $tagID) {
            $attendance = $this->guildService->getAttendanceLazy(
                guildTagID: $tagID,
                startDate: $this->startDate,
                endDate: $this->endDate,
                playerNames: $this->playerNames,
                zoneID: $this->zoneID,
            );

            $stats = $this->calculateFromAttendance($attendance);
            $statsSets->push($stats);
        }

        return $this->aggregate($statsSets);
    }

    /**
     * Calculate attendance statistics from the provided attendance data.
     *
     * @param  iterable<GuildAttendance>  $attendance
     * @return Collection<int, PlayerAttendanceStats>
     */
    protected function calculateFromAttendance(iterable $attendance): Collection
    {
        // Collect all attendance records and sort by date ascending
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
     * Get the attendance records sorted by date ascending.
     *
     * @param  iterable<GuildAttendance>  $attendance
     * @return Collection<int, GuildAttendance>
     */
    protected function sortAttendanceData(iterable $attendance): Collection
    {
        return collect($attendance)->sortBy(fn (GuildAttendance $a) => $a->startTime)->values();
    }

    /**
     * Get the first attendance date for a specific player.
     */
    public function getPlayerFirstAttendanceDate(string $playerName): ?Carbon
    {
        foreach ($this->attendance as $record) {
            foreach ($record->players as $player) {
                if ($player->name === $playerName) {
                    return $record->startTime;
                }
            }
        }

        return null;
    }

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
        /** @var array<string, array{firstAttendance: Carbon, totalReports: int, reportsAttended: int}> $aggregated */
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
