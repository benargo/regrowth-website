<?php

namespace App\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DataTable
{
    /**
     * Memoised output of mergeLinkedReports() + sortReportClusters() for the loaded reports.
     *
     * @var Collection<int, ReportClusterData>|null
     */
    private ?Collection $records = null;

    public function __construct(
        private Calculator $calculator,
        private FiltersData $filters
    ) {}

    /**
     * Get the raid column metadata (one entry per merged-linked-reports group), in reverse-chronological order.
     *
     * @return Collection<int, array{id: string, code: string|null, dayOfWeek: string, date: string, zoneName: string|null}>
     */
    public function columns(): Collection
    {
        $timezone = config('app.timezone');

        return $this->records()
            ->map(fn (ReportClusterData $cluster) => [
                'id' => $cluster->id(),
                'code' => $cluster->code(),
                'dayOfWeek' => $cluster->startTime()->copy()->setTimezone($timezone)->format('D'),
                'date' => $cluster->startTime()->copy()->setTimezone($timezone)->format('d/m'),
                'zoneName' => $cluster->zoneName(),
            ])
            ->reverse()
            ->values();
    }

    /**
     * Get per-character attendance rows, sorted alphabetically by character name.
     *
     * Attendance values: null = before the character's first raid, 0 = absent, 1 = present, 2 = late.
     * Percentage excludes raids covered by a planned absence.
     *
     * @return Collection<int, CharacterAttendanceRowData>
     */
    public function rows(): Collection
    {
        $records = $this->records();

        if ($records->isEmpty()) {
            return collect();
        }

        /** @var array<int, array{startTime: Carbon, players: Collection<string, PlayerPresenceData>}> $clusterSnapshots */
        $clusterSnapshots = $records->values()->map(fn (ReportClusterData $cluster) => [
            'startTime' => $cluster->startTime(),
            'players' => $cluster->players(),
        ])->all();

        /** @var array<string, array{characterId: int, firstIndex: int}> $characterInfo */
        $characterInfo = [];

        foreach ($clusterSnapshots as $index => $snapshot) {
            foreach ($snapshot['players'] as $name => $player) {
                if (! isset($characterInfo[$name])) {
                    $characterInfo[$name] = [
                        'characterId' => $player->character->id,
                        'firstIndex' => $index,
                    ];
                }
            }
        }

        $characterIds = array_map(fn (array $info) => $info['characterId'], $characterInfo);

        $characters = Character::whereIn('id', $characterIds)
            ->with(['rank', 'linkedCharacters', 'playableClass'])
            ->get()
            ->keyBy('id');

        if ($this->filters->includeLinkedCharacters) {
            $resolvedIds = $this->resolvedRankIds();

            $characters = $characters->filter(
                function (Character $c) use ($resolvedIds) {
                    if (in_array($c->rank_id, $resolvedIds, true)) {
                        return true;
                    }

                    return $c->linkedCharacters->contains(
                        fn (Character $linked) => in_array($linked->rank_id, $resolvedIds, true)
                    );
                }
            );
        }

        $absencesByCharacterId = PlannedAbsence::whereIn('character_id', $characterIds)
            ->get()
            ->groupBy('character_id');

        $reversedSnapshots = array_reverse($clusterSnapshots);
        $totalClusters = count($clusterSnapshots);

        $rows = [];

        foreach ($characterInfo as $name => $info) {
            $character = $characters[$info['characterId']] ?? null;

            if ($character === null) {
                continue;
            }

            $totalReports = 0;
            $reportsAttended = 0;
            $attendance = [];
            $plannedAbsences = [];

            foreach ($reversedSnapshots as $reverseIndex => $snapshot) {
                $originalIndex = $totalClusters - 1 - $reverseIndex;

                $coveringAbsence = $this->calculator->isCoveredByPlannedAbsence(
                    $absencesByCharacterId->get($character->id, collect()),
                    $snapshot['startTime'],
                );

                if ($originalIndex < $info['firstIndex']) {
                    $attendance[] = null;
                    $plannedAbsences[] = null;

                    continue;
                }

                $player = $snapshot['players'][$name] ?? null;
                $presence = $player?->presence;
                $attended = in_array($presence, [1, 2], true);

                $attendance[] = $attended ? $presence : 0;
                $plannedAbsences[] = $coveringAbsence;

                if ($coveringAbsence === null) {
                    $totalReports++;

                    if ($attended) {
                        $reportsAttended++;
                    }
                }
            }

            $percentage = $totalReports > 0 ? round(($reportsAttended / $totalReports) * 100, 2) : 0.0;

            $rows[] = new CharacterAttendanceRowData(
                character: $character,
                percentage: $percentage,
                attendance: $attendance,
                plannedAbsences: $plannedAbsences,
            );
        }

        usort($rows, fn (CharacterAttendanceRowData $a, CharacterAttendanceRowData $b) => strcmp($a->character->name, $b->character->name));

        return collect($rows);
    }

    /**
     * Return rows with linked-character merging applied when the filter requests it.
     *
     * @return Collection<int, CharacterAttendanceRowData>
     */
    public function mergedRows(): Collection
    {
        $rows = $this->rows();

        if ($this->filters->includeLinkedCharacters && $rows->isNotEmpty()) {
            $rows = $this->mergeLinkedCharacters($rows, $this->resolvedRankIds(), $this->columns()->count());
        }

        return $rows;
    }

    /**
     * The resolved rank IDs used for the character eager-load filter, exposed so Matrix can reuse them for alt merging.
     *
     * @return array<int, int>
     */
    public function resolvedRankIds(): array
    {
        return ! empty($this->filters->rankIds)
            ? $this->filters->rankIds
            : GuildRank::where('count_attendance', true)->pluck('id')->toArray();
    }

    /**
     * Collapse alt character rows into their main character's row.
     *
     * Only characters flagged as is_main appear in the output. Their linked alts'
     * attendance is aggregated per raid using: 1 (present) > 2 (late) > 0 (absent) > null.
     *
     * @param  Collection<int, CharacterAttendanceRowData>  $rows
     * @param  array<int, int>  $rankIds  Only mains whose rank_id is in this list will appear in output.
     * @return Collection<int, CharacterAttendanceRowData>
     */
    private function mergeLinkedCharacters(Collection $rows, array $rankIds, int $raidCount): Collection
    {
        $rowsById = $rows->keyBy(fn (CharacterAttendanceRowData $row) => $row->character->id);

        $altToMainId = [];
        $mainToAltIds = [];

        foreach ($rowsById as $row) {
            $character = $row->character;

            if (! $character->is_main) {
                $main = $character->linkedCharacters->firstWhere('is_main', true);

                if ($main !== null) {
                    $altToMainId[$character->id] = $main->id;
                    $mainToAltIds[$main->id][] = $character->id;
                }
            }
        }

        $mainIdsInMatrix = $rowsById
            ->filter(fn (CharacterAttendanceRowData $row) => $row->character->is_main)
            ->keys()
            ->all();

        $mainIdsNotInMatrix = array_values(array_diff(array_unique(array_values($altToMainId)), $mainIdsInMatrix));

        $missingMains = Character::whereIn('id', $mainIdsNotInMatrix)->get()->keyBy('id');

        $result = [];

        foreach ($rowsById as $id => $row) {
            /** @var CharacterAttendanceRowData $row */
            if (isset($altToMainId[$id])) {
                continue;
            }

            if (! $row->character->is_main) {
                continue;
            }

            if (! empty($rankIds) && ! in_array($row->character->rank_id, $rankIds, true)) {
                continue;
            }

            $altRows = array_values(array_filter(
                array_map(fn ($altId) => $rowsById->get($altId), $mainToAltIds[$id] ?? []),
            ));

            $result[] = $this->buildMergedRow($row, $altRows, $raidCount);
        }

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

            $syntheticRow = new CharacterAttendanceRowData(
                character: $mainChar,
                percentage: 0.0,
                attendance: array_fill(0, $raidCount, null),
                plannedAbsences: array_fill(0, $raidCount, null),
            );

            $result[] = $this->buildMergedRow($syntheticRow, $altRows, $raidCount);
        }

        usort($result, fn (CharacterAttendanceRowData $a, CharacterAttendanceRowData $b) => strcmp($a->character->name, $b->character->name));

        return collect($result);
    }

    /**
     * Merge a main character's row with their alts' rows into a single combined row.
     *
     * For each raid position the winning value is the lowest non-null presence value across all
     * characters: 1 (present) beats 2 (late) beats 0 (absent); all-null stays null.
     * attendanceNames lists the names of characters who contributed a presence value (1 or 2).
     *
     * @param  array<int, CharacterAttendanceRowData>  $altRows
     */
    private function buildMergedRow(CharacterAttendanceRowData $mainRow, array $altRows, int $raidCount): CharacterAttendanceRowData
    {
        $allRows = [$mainRow, ...$altRows];
        $attendance = [];
        $attendanceNames = [];
        $totalReports = 0;
        $reportsAttended = 0;

        for ($i = 0; $i < $raidCount; $i++) {
            $values = array_map(fn (CharacterAttendanceRowData $row) => $row->attendance[$i] ?? null, $allRows);
            $nonNull = array_values(array_filter($values, fn ($v) => $v !== null));

            if (empty($nonNull)) {
                $attendance[] = null;
                $attendanceNames[] = [];

                continue;
            }

            $coveringAbsence = $mainRow->plannedAbsences[$i] ?? null;
            $presenceValues = array_filter($nonNull, fn ($v) => in_array($v, [1, 2], true));

            if (! empty($presenceValues)) {
                $attendance[] = min($presenceValues);

                $names = [];

                foreach ($allRows as $row) {
                    /** @var CharacterAttendanceRowData $row */
                    if (in_array($row->attendance[$i] ?? null, [1, 2], true)) {
                        $names[] = $row->character->name;
                    }
                }

                $attendanceNames[] = $names;
            } else {
                $attendance[] = 0;
                $attendanceNames[] = [];
            }

            if ($coveringAbsence === null) {
                $totalReports++;

                if (! empty($presenceValues)) {
                    $reportsAttended++;
                }
            }
        }

        $percentage = $totalReports > 0 ? round(($reportsAttended / $totalReports) * 100, 2) : 0.0;

        return new CharacterAttendanceRowData(
            character: $mainRow->character,
            percentage: $percentage,
            attendance: $attendance,
            plannedAbsences: $mainRow->plannedAbsences,
            attendanceNames: $attendanceNames,
        );
    }

    /**
     * Memoised merged + sorted report clusters for the loaded reports.
     *
     * @return Collection<int, ReportClusterData>
     */
    private function records(): Collection
    {
        if ($this->records !== null) {
            return $this->records;
        }

        return $this->records = $this->calculator->sortReportClusters(
            $this->calculator->mergeLinkedReports($this->reportQuery()->get()),
        );
    }

    /**
     * Build the eager-loaded Report query honouring the Filters state.
     */
    private function reportQuery(): Builder
    {
        $query = Report::query();

        if (! empty($this->filters->guildTagIds)) {
            $query->whereIn('guild_tag_id', $this->filters->guildTagIds);
        } else {
            $query->whereHas('guildTag', fn ($q) => $q->where('count_attendance', true));
        }

        if ($this->filters->zoneIds !== null) {
            $query->whereIn('zone_id', $this->filters->zoneIds);
        }

        if ($this->filters->sinceDate !== null) {
            $query->where('start_time', '>=', $this->filters->sinceDate);
        }

        if ($this->filters->beforeDate !== null) {
            $query->where('start_time', '<', $this->filters->beforeDate);
        }

        if ($this->filters->includeLinkedCharacters) {
            $query->with(['characters', 'linkedReports', 'zone']);
        } else {
            $resolvedRankIds = $this->resolvedRankIds();

            $query->with([
                'characters' => fn ($q) => $q->whereHas('rank', fn ($q2) => $q2->whereIn('id', $resolvedRankIds)),
                'linkedReports',
                'zone',
            ]);
        }

        return $query;
    }
}
