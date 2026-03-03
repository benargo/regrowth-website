<?php

namespace App\Services\AttendanceCalculator;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\WarcraftLogs\Report;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceMatrix
{
    /**
     * The raids that form the columns of the matrix, in chronological order.
     *
     * @var array<int, array{code: string, dayOfWeek: string, date: string, zoneName: string|null}>
     */
    public array $raids;

    /**
     * The per-character attendance rows.
     *
     * Attendance values: null = before first raid, 0 = absent, 1 = present, 2 = late.
     * When linked characters are merged, attendance_names lists the character names who attended each raid.
     *
     * @var array<int, array{name: string, id: int, rank_id: int|null, percentage: float, attendance: array<int, int|null>, attendance_names?: array<int, array<int, string>>}>
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

        if ($filters->zoneIds !== null) {
            $query->whereIn('zone_id', $filters->zoneIds);
        }

        if ($filters->sinceDate !== null) {
            $query->where('start_time', '>=', $filters->sinceDate);
        }

        if ($filters->beforeDate !== null) {
            $query->where('start_time', '<', $filters->beforeDate);
        }

        $resolvedRankIds = ! empty($filters->rankIds)
            ? $filters->rankIds
            : GuildRank::where('count_attendance', true)->pluck('id')->toArray();

        if ($filters->includeLinkedCharacters) {
            // Load all characters regardless of rank so alts from any rank
            // can contribute attendance. mergeLinkedCharacters() will filter
            // the output to only mains from the resolved rank IDs.
            $query->with('characters');
        } else {
            $query->with(['characters' => fn ($q) => $q->whereHas('rank', fn ($q2) => $q2->whereIn('id', $resolvedRankIds))]);
        }

        $this->calculateMatrix($query->get());

        if ($filters->includeLinkedCharacters && ! empty($this->rows)) {
            $this->rows = $this->mergeLinkedCharacters($resolvedRankIds);
        }

        return $this;
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
        /** @var array<int, array{code: string, startTime: Carbon, zoneName: string|null, players: array<string, array{id: int, rank_id: int|null, presence: int}>}> $raidRecords */
        $raidRecords = $reports->map(fn (Report $report) => [
            'code' => $report->code,
            'startTime' => $report->start_time,
            'zoneName' => $report->zone_name,
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
            'zoneName' => $record['zoneName'],
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

    /**
     * Collapse alt character rows into their main character's row.
     *
     * Only characters flagged as is_main appear in the output. Their linked alts'
     * attendance is aggregated per raid using: 1 (present) > 2 (late) > 0 (absent) > null.
     *
     * @param  array<int, int>  $rankIds  Only mains whose rank_id is in this list will appear in output.
     * @return array<int, array{name: string, id: int, rank_id: int|null, playable_class: array|null, percentage: float, attendance: array<int, int|null>, attendance_names: array<int, array<int, string>>}>
     */
    protected function mergeLinkedCharacters(array $rankIds = []): array
    {
        $raidCount = count($this->raids);
        $rowsById = collect($this->rows)->keyBy('id');
        $characterIds = $rowsById->keys()->all();

        // Load all matrix characters with their linked characters to resolve is_main and alt relationships.
        $characters = Character::whereIn('id', $characterIds)
            ->with('linkedCharacters')
            ->get()
            ->keyBy('id');

        // Build alt → main and main → alts maps by inspecting each alt's linked characters.
        $altToMainId = [];
        $mainToAltIds = [];

        foreach ($characters as $character) {
            if (! $character->is_main) {
                $main = $character->linkedCharacters->firstWhere('is_main', true);

                if ($main !== null) {
                    $altToMainId[$character->id] = $main->id;
                    $mainToAltIds[$main->id][] = $character->id;
                }
            }
        }

        // Some mains may not have attended any raids themselves (so no row in the matrix),
        // but their alts may have. Load those missing mains separately.
        $mainIdsInMatrix = $characters->filter(fn ($c) => $c->is_main)->keys()->all();
        $mainIdsNotInMatrix = array_values(array_diff(array_unique(array_values($altToMainId)), $mainIdsInMatrix));

        $missingMains = Character::whereIn('id', $mainIdsNotInMatrix)->get()->keyBy('id');

        // Build merged rows for all mains present in the matrix.
        $result = [];

        foreach ($rowsById as $id => $row) {
            if (isset($altToMainId[$id])) {
                continue;
            }

            if (! ($characters[$id]->is_main ?? false)) {
                continue;
            }

            if (! empty($rankIds) && ! in_array($row['rank_id'], $rankIds, true)) {
                continue;
            }

            $altRows = array_values(array_filter(
                array_map(fn ($altId) => $rowsById->get($altId), $mainToAltIds[$id] ?? []),
            ));

            $result[] = $this->buildMergedRow($row, $altRows, $raidCount);
        }

        // Create synthetic rows for mains who never raided but whose alts did.
        foreach ($mainIdsNotInMatrix as $mainId) {
            $altIds = $mainToAltIds[$mainId] ?? [];

            if (empty($altIds)) {
                continue;
            }

            $mainChar = $missingMains->get($mainId);

            if ($mainChar === null) {
                continue;
            }

            if (! empty($rankIds) && ! in_array($mainChar->rank_id, $rankIds, true)) {
                continue;
            }

            $altRows = array_values(array_filter(
                array_map(fn ($altId) => $rowsById->get($altId), $altIds),
            ));

            $syntheticRow = [
                'name' => $mainChar->name,
                'id' => $mainChar->id,
                'rank_id' => $mainChar->rank_id,
                'playable_class' => $mainChar->playable_class,
                'percentage' => 0.0,
                'attendance' => array_fill(0, $raidCount, null),
            ];

            $result[] = $this->buildMergedRow($syntheticRow, $altRows, $raidCount);
        }

        usort($result, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * Merge a main character's row with their alts' rows into a single combined row.
     *
     * For each raid position the winning value is the lowest non-null presence value across all
     * characters: 1 (present) beats 2 (late) beats 0 (absent); all-null stays null.
     * attendance_names lists the names of characters who contributed a presence value (1 or 2).
     *
     * @param  array{name: string, id: int, rank_id: int|null, playable_class: array|null, percentage: float, attendance: array<int, int|null>}  $mainRow
     * @param  array<int, array{name: string, id: int, rank_id: int|null, playable_class: array|null, percentage: float, attendance: array<int, int|null>}>  $altRows
     * @return array{name: string, id: int, rank_id: int|null, playable_class: array|null, percentage: float, attendance: array<int, int|null>, attendance_names: array<int, array<int, string>>}
     */
    protected function buildMergedRow(array $mainRow, array $altRows, int $raidCount): array
    {
        $allRows = [$mainRow, ...$altRows];
        $attendance = [];
        $attendanceNames = [];
        $totalReports = 0;
        $reportsAttended = 0;

        for ($i = 0; $i < $raidCount; $i++) {
            $values = array_map(fn ($row) => $row['attendance'][$i] ?? null, $allRows);
            $nonNull = array_values(array_filter($values, fn ($v) => $v !== null));

            if (empty($nonNull)) {
                $attendance[] = null;
                $attendanceNames[] = [];

                continue;
            }

            $totalReports++;
            $presenceValues = array_filter($nonNull, fn ($v) => in_array($v, [1, 2], true));

            if (! empty($presenceValues)) {
                $attendance[] = min($presenceValues);
                $reportsAttended++;

                $names = [];

                foreach ($allRows as $row) {
                    if (in_array($row['attendance'][$i] ?? null, [1, 2], true)) {
                        $names[] = $row['name'];
                    }
                }

                $attendanceNames[] = $names;
            } else {
                $attendance[] = 0;
                $attendanceNames[] = [];
            }
        }

        $percentage = $totalReports > 0 ? round(($reportsAttended / $totalReports) * 100, 2) : 0.0;

        return [
            'name' => $mainRow['name'],
            'id' => $mainRow['id'],
            'rank_id' => $mainRow['rank_id'],
            'playable_class' => $mainRow['playable_class'] ?? null,
            'percentage' => $percentage,
            'attendance' => $attendance,
            'attendance_names' => $attendanceNames,
        ];
    }
}
