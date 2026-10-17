<?php

namespace App\Actions\Attendance;

use App\Data\Attendance\CharacterAttendanceData;
use App\Models\Character;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Folds each alt's attendance row into its main's row.
 */
class CombineLinkedCharacters
{
    use AsAction;

    /**
     * @param  Collection<int, CharacterAttendanceData>  $rows  Characters loaded with `linkedCharacters`.
     * @param  array<int, int>  $rankIds
     * @return Collection<int, CharacterAttendanceData>
     */
    public function handle(Collection $rows, array $rankIds, int $raidCount): Collection
    {
        $rowsById = $rows->keyBy(fn (CharacterAttendanceData $row): int => $row->character->id);

        $altRowsByMainId = $rowsById
            ->reject(fn (CharacterAttendanceData $row): bool => $row->character->is_main)
            ->groupBy(fn (CharacterAttendanceData $row): ?int => $row->character->linkedCharacters->firstWhere('is_main', true)?->id)
            ->forget('');

        $mainRows = $rowsById->filter(fn (CharacterAttendanceData $row): bool => $row->character->is_main);

        $missingMains = $this->loadMissingMains(array_diff($altRowsByMainId->keys()->all(), $mainRows->keys()->all()));

        return $mainRows->values()
            ->concat($missingMains->map(fn (Character $main): CharacterAttendanceData => $this->emptyRow($main, $altRowsByMainId->get($main->id, collect())->all(), $raidCount)))
            ->filter(fn (CharacterAttendanceData $row): bool => in_array($row->character->rank_id, $rankIds, true))
            ->map(fn (CharacterAttendanceData $row): CharacterAttendanceData => $this->merge($row, $altRowsByMainId->get($row->character->id, collect())->all(), $raidCount))
            ->sortBy(fn (CharacterAttendanceData $row): string => $row->character->name, SORT_STRING)
            ->values();
    }

    /**
     * Load the main characters with the given IDs, with their linked characters, class media and rank.
     *
     * @param  array<int, int>  $mainIds
     * @return EloquentCollection<int, Character>
     */
    private function loadMissingMains(array $mainIds): EloquentCollection
    {
        return Character::whereIn('id', $mainIds)
            ->with(['linkedCharacters', 'playableClass.media', 'rank'])
            ->get();
    }

    /**
     * @param  array<int, CharacterAttendanceData>  $altRows
     */
    private function emptyRow(Character $main, array $altRows, int $raidCount): CharacterAttendanceData
    {
        return new CharacterAttendanceData(
            character: $main,
            firstAttendance: collect($altRows)->min(fn (CharacterAttendanceData $row) => $row->firstAttendance),
            totalReports: 0,
            reportsAttended: 0,
            attendance: array_fill(0, $raidCount, null),
            plannedAbsences: array_fill(0, $raidCount, null),
        );
    }

    /**
     * @param  array<int, CharacterAttendanceData>  $altRows
     */
    private function merge(CharacterAttendanceData $mainRow, array $altRows, int $raidCount): CharacterAttendanceData
    {
        $allRows = collect([$mainRow, ...$altRows]);
        $attendance = [];
        $attendanceNames = [];
        $totalReports = 0;
        $reportsAttended = 0;

        for ($index = 0; $index < $raidCount; $index++) {
            $best = null;
            $names = [];

            foreach ($allRows as $row) {
                $presence = $row->attendance[$index] ?? null;

                if ($presence === null) {
                    continue;
                }

                if ($best === null || $presence->priority() > $best->priority()) {
                    $best = $presence;
                }

                if ($presence->isAttended()) {
                    $names[] = $row->character->name;
                }
            }

            $attendance[] = $best;
            $attendanceNames[] = $names;

            if ($best === null || ($mainRow->plannedAbsences[$index] ?? null) !== null) {
                continue;
            }

            $totalReports++;

            if ($best->isAttended()) {
                $reportsAttended++;
            }
        }

        return new CharacterAttendanceData(
            character: $mainRow->character,
            firstAttendance: $allRows->min(fn (CharacterAttendanceData $row) => $row->firstAttendance),
            totalReports: $totalReports,
            reportsAttended: $reportsAttended,
            attendance: $attendance,
            plannedAbsences: $mainRow->plannedAbsences,
            attendanceNames: $attendanceNames,
        );
    }
}
