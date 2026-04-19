<?php

namespace App\Services\Attendance;

use App\Models\Character;
use Illuminate\Support\Collection;

class Matrix
{
    public function __construct(
        protected readonly Calculator $calculator
    ) {}

    /**
     * Build the whole-guild attendance matrix, including per-raid presence values per character.
     */
    public function matrixForWholeGuild(): AttendanceMatrix
    {
        return $this->matrixWithFilters(new Filters);
    }

    /**
     * Build the attendance matrix with the given server-side filters applied.
     */
    public function matrixWithFilters(Filters $filters): AttendanceMatrix
    {
        $table = new DataTable($this->calculator, $filters);
        $raids = $table->columns();
        $rows = $table->rows();

        if ($filters->includeLinkedCharacters && $rows->isNotEmpty()) {
            $rows = $this->mergeLinkedCharacters($rows, $table->resolvedRankIds(), $raids->count());
        }

        if ($filters->character !== null) {
            $characterId = $filters->character->id;
            $rows = $rows->filter(fn (CharacterAttendanceRow $row) => $row->character->id === $characterId)->values();
        }

        return new AttendanceMatrix($raids, $rows);
    }

    /**
     * Collapse alt character rows into their main character's row.
     *
     * Only characters flagged as is_main appear in the output. Their linked alts'
     * attendance is aggregated per raid using: 1 (present) > 2 (late) > 0 (absent) > null.
     *
     * @param  Collection<int, CharacterAttendanceRow>  $rows
     * @param  array<int, int>  $rankIds  Only mains whose rank_id is in this list will appear in output.
     * @return Collection<int, CharacterAttendanceRow>
     */
    protected function mergeLinkedCharacters(Collection $rows, array $rankIds, int $raidCount): Collection
    {
        $rowsById = $rows->keyBy(fn (CharacterAttendanceRow $row) => $row->character->id);

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
            ->filter(fn (CharacterAttendanceRow $row) => $row->character->is_main)
            ->keys()
            ->all();

        $mainIdsNotInMatrix = array_values(array_diff(array_unique(array_values($altToMainId)), $mainIdsInMatrix));

        $missingMains = Character::whereIn('id', $mainIdsNotInMatrix)->get()->keyBy('id');

        $result = [];

        foreach ($rowsById as $id => $row) {
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

            $syntheticRow = new CharacterAttendanceRow(
                character: $mainChar,
                percentage: 0.0,
                attendance: array_fill(0, $raidCount, null),
                plannedAbsences: array_fill(0, $raidCount, null),
            );

            $result[] = $this->buildMergedRow($syntheticRow, $altRows, $raidCount);
        }

        usort($result, fn (CharacterAttendanceRow $a, CharacterAttendanceRow $b) => strcmp($a->character->name, $b->character->name));

        return collect($result);
    }

    /**
     * Merge a main character's row with their alts' rows into a single combined row.
     *
     * For each raid position the winning value is the lowest non-null presence value across all
     * characters: 1 (present) beats 2 (late) beats 0 (absent); all-null stays null.
     * attendanceNames lists the names of characters who contributed a presence value (1 or 2).
     *
     * @param  array<int, CharacterAttendanceRow>  $altRows
     */
    protected function buildMergedRow(CharacterAttendanceRow $mainRow, array $altRows, int $raidCount): CharacterAttendanceRow
    {
        $allRows = [$mainRow, ...$altRows];
        $attendance = [];
        $attendanceNames = [];
        $totalReports = 0;
        $reportsAttended = 0;

        for ($i = 0; $i < $raidCount; $i++) {
            $values = array_map(fn (CharacterAttendanceRow $row) => $row->attendance[$i] ?? null, $allRows);
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

        return new CharacterAttendanceRow(
            character: $mainRow->character,
            percentage: $percentage,
            attendance: $attendance,
            plannedAbsences: $mainRow->plannedAbsences,
            attendanceNames: $attendanceNames,
        );
    }
}
