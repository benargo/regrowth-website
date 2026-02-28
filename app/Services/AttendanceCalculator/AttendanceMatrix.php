<?php

namespace App\Services\AttendanceCalculator;

use App\Models\GuildRank;
use App\Models\WarcraftLogs\Report;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceMatrix
{
    /**
     * The raids that form the columns of the matrix, in chronological order.
     *
     * @var array<int, array{code: string, date: string}>
     */
    public array $raids;

    /**
     * The per-character attendance rows.
     *
     * Attendance values: null = before first raid, 0 = absent, 1 = present, 2 = late.
     *
     * @var array<int, array{name: string, id: int, rank_id: int|null, percentage: float, attendance: array<int, int|null>}>
     */
    public array $rows;

    public function __construct(
        protected readonly AttendanceCalculator $calculator,
        protected string $timezone = 'UTC',
    ) {}

    /**
     * Calculate the whole-guild attendance matrix, including per-raid presence values per character.
     */
    public function matrixForWholeGuild(): self
    {
        return $this->matrixWithFilters(new AttendanceMatrixFilters);
    }

    /**
     * Calculate the attendance matrix with the given server-side filters applied.
     */
    public function matrixWithFilters(AttendanceMatrixFilters $filters): self
    {
        $query = Report::query();

        if (! empty($filters->guildTagIds)) {
            $query->whereIn('guild_tag_id', $filters->guildTagIds);
        } else {
            $query->whereHas('guildTag', fn ($q) => $q->where('count_attendance', true));
        }

        if (! empty($filters->zoneIds)) {
            $query->whereIn('zone_id', $filters->zoneIds);
        }

        if ($filters->sinceDate !== null) {
            $query->where('start_time', '>=', $filters->sinceDate);
        }

        if ($filters->beforeDate !== null) {
            $query->where('start_time', '<', $filters->beforeDate);
        }

        $rankIds = ! empty($filters->rankIds)
            ? $filters->rankIds
            : GuildRank::where('count_attendance', true)->pluck('id')->toArray();

        $query->with(['characters' => fn ($q) => $q->whereHas('rank', fn ($q2) => $q2->whereIn('id', $rankIds))]);

        return $this->calculateMatrix($query->get());
    }

    /**
     * Load the matrix with the given raids and rows.
     */
    public function load(array $raids = [], array $rows = []): self
    {
        $this->raids = $raids;
        $this->rows = $rows;

        return $this;
    }

    /**
     * @return array{raids: array<int, array{code: string, date: string}>, rows: array<int, array{name: string, id: int, rank_id: int|null, percentage: float, attendance: array<int, int|null>}>}
     */
    public function toArray(): array
    {
        return [
            'raids' => $this->raids,
            'rows' => $this->rows,
        ];
    }

    /**
     * Build the attendance matrix from a collection of reports.
     *
     * Returns one column per merged raid day, and one row per character. Attendance
     * values are: null = before the character's first raid, 0 = absent, 1 = present, 2 = late.
     *
     * @param  Collection<int, Report>  $reports
     */
    protected function calculateMatrix(Collection $reports): self
    {
        /** @var array<int, array{code: string, startTime: Carbon, players: array<string, array{id: int, rank_id: int|null, presence: int}>}> $raidRecords */
        $raidRecords = $reports->map(fn (Report $report) => [
            'code' => $report->code,
            'startTime' => $report->start_time,
            'players' => $report->characters->mapWithKeys(fn ($character) => [
                $character->name => [
                    'id' => $character->id,
                    'rank_id' => $character->rank_id,
                    'playable_class' => $character->playable_class ?? null,
                    'presence' => $character->pivot->presence,
                ],
            ])->all(),
        ])->all();

        $records = $this->calculator->sortRaidRecords(collect($raidRecords));
        $records = $this->calculator->mergeByRaidDay($records);

        if ($records->isEmpty()) {
            return $this->load([], []);
        }

        // Build the ordered raids list (columns).
        $raids = $records->map(fn (array $record) => [
            'code' => $record['code'],
            'dayOfWeek' => $record['startTime']->copy()->setTimezone($this->timezone)->format('D'),
            'date' => $record['startTime']->copy()->setTimezone($this->timezone)->format('d/m'),
        ])->reverse()->values()->all();

        // First pass: find each character's ID, rank, and first-appearance index.
        /** @var array<string, array{id: int, rank_id: int|null, playable_class: array|null, firstIndex: int}> $characterInfo */
        $characterInfo = [];

        foreach ($records->values() as $index => $record) {
            foreach ($record['players'] as $name => $playerData) {
                if (! isset($characterInfo[$name])) {
                    $characterInfo[$name] = [
                        'id' => $playerData['id'],
                        'rank_id' => $playerData['rank_id'] ?? null,
                        'playable_class' => $playerData['playable_class'] ?? null,
                        'firstIndex' => $index,
                    ];
                }
            }
        }

        // Second pass: build per-character attendance arrays and calculate percentages.
        $rows = [];

        foreach ($characterInfo as $name => $info) {
            $totalReports = 0;
            $reportsAttended = 0;
            $attendance = [];

            foreach ($records->values()->reverse() as $index => $record) {
                if ($index < $info['firstIndex']) {
                    $attendance[] = null;

                    continue;
                }

                $totalReports++;

                $presence = $record['players'][$name]['presence'] ?? null;

                if (in_array($presence, [1, 2], true)) {
                    $reportsAttended++;
                    $attendance[] = $presence;
                } else {
                    $attendance[] = 0;
                }
            }

            $percentage = $totalReports > 0 ? round(($reportsAttended / $totalReports) * 100, 2) : 0.0;

            $rows[] = [
                'name' => $name,
                'id' => $info['id'],
                'rank_id' => $info['rank_id'],
                'playable_class' => $info['playable_class'] ?? null,
                'percentage' => $percentage,
                'attendance' => $attendance,
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $this->load($raids, $rows);
    }
}
