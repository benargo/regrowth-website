<?php

namespace App\Services\Attendance;

use App\Exceptions\EmptyCollectionException;
use App\Models\Character;
use App\Models\CharacterReport;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Calculator
{
    /**
     * Calculate attendance for every character whose rank counts towards attendance.
     *
     * @return Collection<int, CharacterAttendanceStatsData>
     *
     * @throws EmptyCollectionException if no ranks are configured to count towards attendance
     */
    public function wholeGuild(): Collection
    {
        $ranks = GuildRank::hydrate(
            Cache::tags(['attendance'])->remember(
                'guild_ranks:where_count_attendance',
                now()->addDay(),
                fn () => GuildRank::where('count_attendance', true)->get()->toArray()
            )
        );

        if ($ranks->isEmpty()) {
            throw new EmptyCollectionException('At least one rank must be specified to calculate attendance for specific ranks.');
        }

        return $this->forRanks($ranks);
    }

    /**
     * Calculate attendance for characters in the specified ranks. Only characters who have attended at least one report while in these ranks will be included.
     *
     * @param  Collection<int, GuildRank>  $ranks
     * @return Collection<int, CharacterAttendanceStatsData>
     *
     * @throws EmptyCollectionException if the provided collection of ranks is empty
     */
    public function forRanks(Collection $ranks): Collection
    {
        if ($ranks->isEmpty()) {
            throw new EmptyCollectionException('At least one rank must be specified to calculate attendance for specific ranks.');
        }

        $rankIds = $ranks->pluck('id');

        $reports = Report::whereHas('guildTag', fn ($query) => $query->where('count_attendance', true))
            ->with([
                'characters' => fn ($query) => $query->whereHas('rank', fn ($q) => $q->whereIn('id', $rankIds)),
                'linkedReports',
                'zone',
            ])
            ->get();

        return $this->calculate($reports);
    }

    /**
     * Calculate attendance for a specific character. Only reports attended while the character was in a rank that counts towards attendance will be included.
     *
     * @return Collection<int, CharacterAttendanceStatsData>
     */
    public function forCharacter(Character $character): Collection
    {
        $reports = Report::whereHas('guildTag', fn ($query) => $query->where('count_attendance', true))
            ->whereHas('characters', fn ($query) => $query->where('id', $character->id)->whereHas('rank', fn ($q) => $q->where('count_attendance', true)))
            ->with([
                'characters' => fn ($query) => $query->where('id', $character->id)->whereHas('rank', fn ($q) => $q->where('count_attendance', true)),
                'linkedReports',
                'zone',
            ])
            ->get();

        return $this->calculate($reports);
    }

    /**
     * Calculate attendance for a specific report. Only characters in the report who were in ranks that count towards attendance will be included.
     *
     * @return Collection<int, CharacterAttendanceStatsData>
     */
    public function forReport(Report $report): Collection
    {
        if (! $report->guildTag?->count_attendance) {
            return collect();
        }

        $report->load([
            'characters' => fn ($query) => $query->whereHas('rank', fn ($q) => $q->where('count_attendance', true)),
            'linkedReports',
            'zone',
        ]);

        return $this->calculate(collect([$report]));
    }

    /**
     * Calculate attendance statistics by querying the database pivot table.
     *
     * Fetches all qualifying ranks and reports, merges linked reports, and
     * calculates each character's attendance percentage from their first appearance.
     *
     * @param  Collection<int, Report>  $reports
     * @return Collection<int, CharacterAttendanceStatsData>
     */
    protected function calculate(Collection $reports): Collection
    {
        $clusters = $this->sortReportClusters($this->mergeLinkedReports($reports));

        if ($clusters->isEmpty()) {
            return collect();
        }

        $clusterSnapshots = $clusters->map(fn (ReportClusterData $cluster) => [
            'startTime' => $cluster->startTime(),
            'players' => $cluster->players(),
        ])->values()->all();

        /** @var array<string, array{character: Character, firstAttendance: Carbon}> $characterInfo */
        $characterInfo = [];

        foreach ($clusterSnapshots as $snapshot) {
            foreach ($snapshot['players'] as $name => $playerPresence) {
                if (! isset($characterInfo[$name])) {
                    $characterInfo[$name] = [
                        'character' => $playerPresence->character,
                        'firstAttendance' => $snapshot['startTime'],
                    ];
                }
            }
        }

        $characterIds = array_map(fn (array $info) => $info['character']->id, $characterInfo);

        $absencesByCharacterId = PlannedAbsence::whereIn('character_id', $characterIds)
            ->get()
            ->groupBy('character_id');

        $stats = [];

        foreach ($characterInfo as $characterName => $info) {
            $totalReports = 0;
            $reportsAttended = 0;

            foreach ($clusterSnapshots as $snapshot) {
                if ($snapshot['startTime']->lt($info['firstAttendance'])) {
                    continue;
                }

                if ($this->isCoveredByPlannedAbsence($absencesByCharacterId->get($info['character']->id, collect()), $snapshot['startTime'])) {
                    continue;
                }

                $totalReports++;

                $playerPresence = $snapshot['players'][$characterName] ?? null;

                if ($playerPresence !== null && in_array($playerPresence->presence, [1, 2], true)) {
                    $reportsAttended++;
                }
            }

            $percentage = $totalReports > 0 ? ($reportsAttended / $totalReports) * 100 : 0.0;

            $stats[$characterName] = new CharacterAttendanceStatsData(
                character: $info['character'],
                firstAttendance: $info['firstAttendance'],
                totalReports: $totalReports,
                reportsAttended: $reportsAttended,
                percentage: round($percentage, 2),
            );
        }

        return (new Collection($stats))
            ->sortBy(fn (CharacterAttendanceStatsData $s) => $s->character->name)
            ->values();
    }

    /**
     * Average attendance percentage across the provided rows.
     *
     * Returns null when there are no rows so callers can distinguish "no data" from "0% attendance".
     *
     * @param  array<int, CharacterAttendanceRowData>  $rows
     */
    public function averageAttendance(array $rows): ?float
    {
        if (count($rows) === 0) {
            return null;
        }

        $sum = 0.0;

        foreach ($rows as $row) {
            $sum += $row->percentage;
        }

        return round($sum / count($rows), 1);
    }

    /**
     * Identify players whose recent attendance (last four raids) has dropped by at least 25 percentage points from their overall percentage.
     *
     * Requires at least three recent non-null attendance values and an overall percentage of at least 25 to qualify.
     *
     * @param  array<int, CharacterAttendanceRowData>  $rows
     * @return array<int, CharacterAttendanceRowData>
     */
    public function findDroppingOff(array $rows): array
    {
        $droppingOff = [];

        foreach ($rows as $row) {
            $recentRate = $this->recentAttendanceRate($row->attendance);

            if ($recentRate === null) {
                continue;
            }

            if ($row->percentage >= 25 && ($row->percentage - $recentRate) >= 25) {
                $droppingOff[] = $row;
            }
        }

        return $droppingOff;
    }

    /**
     * Identify players whose recent attendance (last four raids) has improved by at least 25 percentage points over their overall percentage.
     *
     * Requires at least three recent non-null attendance values and an overall percentage of at most 75 to qualify.
     *
     * @param  array<int, CharacterAttendanceRowData>  $rows
     * @return array<int, CharacterAttendanceRowData>
     */
    public function findPickingUp(array $rows): array
    {
        $pickingUp = [];

        foreach ($rows as $row) {
            $recentRate = $this->recentAttendanceRate($row->attendance);

            if ($recentRate === null) {
                continue;
            }

            if ($row->percentage <= 75 && ($recentRate - $row->percentage) >= 25) {
                $pickingUp[] = $row;
            }
        }

        return $pickingUp;
    }

    /**
     * Build a list of characters who were benched (presence = 2) in any report started in the past seven days, grouped by the report's guild tag name.
     *
     * Characters appear only once per group, sorted alphabetically by name. Reports without a guild tag are grouped under "Untagged".
     *
     * @return array<string, array<int, array{name: string, playable_class: mixed}>>
     */
    public function benchedLastWeek(): array
    {
        $rows = CharacterReport::query()
            ->where('presence', 2)
            ->whereHas('report', fn ($q) => $q->where('start_time', '>=', now()->subDays(7)))
            ->with(['character:id,name,playable_class', 'report:id,guild_tag_id', 'report.guildTag:id,name'])
            ->get();

        return $rows
            ->groupBy(fn (CharacterReport $cr) => $cr->report?->guildTag?->name ?? 'Untagged')
            ->map(fn ($group) => $group
                ->map(fn (CharacterReport $cr) => $cr->character)
                ->filter()
                ->unique(fn (Character $c) => $c->id)
                ->sortBy('name')
                ->map(fn (Character $c) => [
                    'name' => $c->name,
                    'playable_class' => $c->playable_class,
                ])
                ->values()
                ->all())
            ->sortKeys()
            ->toArray();
    }

    /**
     * Merge reports that are linked together in the database into ReportClusterData value objects.
     *
     * Reports connected via the raid_report_links table are treated as a single
     * raid. Uses union-find (keyed on report id, since code is nullable for manual
     * reports) to resolve connected components within the provided collection.
     *
     * @param  Collection<int, Report>  $reports  Reports with 'characters' and 'linkedReports' eager loaded.
     * @return Collection<int, ReportClusterData>
     */
    public function mergeLinkedReports(Collection $reports): Collection
    {
        if ($reports->isEmpty()) {
            return collect();
        }

        $ids = $reports->pluck('id')->all();
        $idSet = array_flip($ids);

        $parent = array_combine($ids, $ids);

        $find = function (string $id) use (&$parent): string {
            $root = $id;

            while ($parent[$root] !== $root) {
                $root = $parent[$root];
            }

            while ($parent[$id] !== $root) {
                $next = $parent[$id];
                $parent[$id] = $root;
                $id = $next;
            }

            return $root;
        };

        foreach ($reports as $report) {
            foreach ($report->linkedReports as $linked) {
                if (! isset($idSet[$linked->id])) {
                    continue;
                }

                $rootA = $find($report->id);
                $rootB = $find($linked->id);

                if ($rootA !== $rootB) {
                    $parent[$rootB] = $rootA;
                }
            }
        }

        $groups = [];
        foreach ($reports as $report) {
            $root = $find($report->id);
            $groups[$root][] = $report;
        }

        return collect($groups)
            ->map(fn (array $group) => new ReportClusterData(collect($group)))
            ->values();
    }

    /**
     * Determine whether a raid's start time falls within any of the character's planned absences.
     *
     * Compares by calendar date in the configured timezone, since planned absences represent days rather than exact times.
     * Returns the matching PlannedAbsence if covered, or null if not.
     *
     * @param  Collection<int, PlannedAbsence>  $absences
     */
    public function isCoveredByPlannedAbsence(Collection $absences, Carbon $startTime): ?PlannedAbsence
    {
        $raidDate = $startTime->copy()->setTimezone(config('app.timezone'))->startOfDay();

        foreach ($absences as $absence) {
            $absenceStart = $absence->start_date->copy()->startOfDay();
            $absenceEnd = $absence->end_date?->copy()->endOfDay();

            if ($raidDate->gte($absenceStart) && ($absenceEnd === null || $raidDate->lte($absenceEnd))) {
                return $absence;
            }
        }

        return null;
    }

    /**
     * Get the priority rank for a presence value (higher = better).
     */
    public static function presencePriority(int $presence): int
    {
        return match ($presence) {
            1 => 2,
            2 => 1,
            default => 0,
        };
    }

    /**
     * Get the report clusters sorted by startTime ascending.
     *
     * @param  Collection<int, ReportClusterData>  $clusters
     * @return Collection<int, ReportClusterData>
     */
    public function sortReportClusters(Collection $clusters): Collection
    {
        return $clusters->sortBy(fn (ReportClusterData $cluster) => $cluster->startTime())->values();
    }

    /**
     * Compute the recent attendance rate (%), using the most recent up-to-four non-null values.
     *
     * Returns null when fewer than three recent values are available, so the caller knows there isn't enough data to draw a trend.
     *
     * @param  array<int, int|null>  $attendance
     */
    protected function recentAttendanceRate(array $attendance): ?float
    {
        $nonNull = array_values(array_filter($attendance, fn ($v) => $v !== null));
        $recent = array_slice($nonNull, 0, 4);

        if (count($recent) < 3) {
            return null;
        }

        $attended = count(array_filter($recent, fn ($v) => in_array($v, [1, 2], true)));

        return ($attended / count($recent)) * 100;
    }
}
