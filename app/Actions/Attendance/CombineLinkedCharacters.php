<?php

namespace App\Actions\Attendance;

use App\Data\Attendance\CharacterAttendanceRowData;
use App\Enums\Presence;
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
     * @var array<int, array<int, CharacterAttendanceRowData>> Indexed by main character ID, then by alt character ID.
     */
    private array $altRowsByMainId = [];

    /**
     * @param  Collection<int, CharacterAttendanceRowData>  $rows  Characters loaded with `linkedCharacters`.
     * @param  array<int, int>  $rankIds
     * @return Collection<int, CharacterAttendanceRowData>
     */
    public function handle(Collection $rows, array $rankIds, int $raidCount): Collection
    {
        $rowsById = $rows->keyBy(fn (CharacterAttendanceRowData $row): int => $row->character->id)
            ->each(function (CharacterAttendanceRowData $row): void {
                $main = $row->character->is_main ? null : $row->character->linkedCharacters->firstWhere('is_main', true);

                if ($main !== null) {
                    $this->altRowsByMainId[$main->id][] = $row;
                }
            });

        $mainRows = $rowsById->filter(fn (CharacterAttendanceRowData $row): bool => $row->character->is_main);

        $missingMains = $this->loadMissingMains(array_diff(array_keys($this->altRowsByMainId), $mainRows->keys()->all()));

        return $mainRows->values()
            ->concat($missingMains->map(fn (Character $main): CharacterAttendanceRowData => $this->emptyRow($main, data_get($this->altRowsByMainId, $main->id, []), $raidCount)))
            ->filter(fn (CharacterAttendanceRowData $row): bool => in_array($row->character->rank_id, $rankIds, true))
            ->map(fn (CharacterAttendanceRowData $row): CharacterAttendanceRowData => $this->merge($row, data_get($this->altRowsByMainId, $row->character->id, []), $raidCount))
            ->sortBy(fn (CharacterAttendanceRowData $row): string => $row->character->name, SORT_STRING)
            ->values();
    }

    /**
     * Load the main characters with the given IDs, with their rank, linked characters and class media.
     *
     * @param  array<int, int>  $mainIds
     * @return EloquentCollection<int, Character>
     */
    private function loadMissingMains(array $mainIds): EloquentCollection
    {
        return Character::whereIn('id', $mainIds)
            ->with(['rank', 'linkedCharacters', 'playableClass.media'])
            ->get();
    }

    /**
     * @param  array<int, CharacterAttendanceRowData>  $altRows
     */
    private function emptyRow(Character $main, array $altRows, int $raidCount): CharacterAttendanceRowData
    {
        return new CharacterAttendanceRowData(
            character: $main,
            firstAttendance: collect($altRows)->min(fn (CharacterAttendanceRowData $row) => $row->firstAttendance),
            totalReports: 0,
            reportsAttended: 0,
            percentage: 0.0,
            attendance: array_fill(0, $raidCount, null),
            plannedAbsences: array_fill(0, $raidCount, null),
        );
    }

    /**
     * @param  array<int, CharacterAttendanceRowData>  $altRows
     */
    private function merge(CharacterAttendanceRowData $mainRow, array $altRows, int $raidCount): CharacterAttendanceRowData
    {
        $allRows = collect([$mainRow, ...$altRows]);
        $attendance = [];
        $attendanceNames = [];
        $totalReports = 0;
        $reportsAttended = 0;

        for ($index = 0; $index < $raidCount; $index++) {
            $best = $allRows
                ->map(fn (CharacterAttendanceRowData $row): ?Presence => $row->attendance[$index] ?? null)
                ->whereNotNull()
                ->sortByDesc(fn (Presence $presence): int => $presence->priority())
                ->first();

            $attendance[] = $best;
            $attendanceNames[] = $allRows
                ->filter(fn (CharacterAttendanceRowData $row): bool => ($row->attendance[$index] ?? null)?->isAttended() ?? false)
                ->map(fn (CharacterAttendanceRowData $row): string => $row->character->name)
                ->values()
                ->all();

            if ($best === null || ($mainRow->plannedAbsences[$index] ?? null) !== null) {
                continue;
            }

            $totalReports++;

            if ($best->isAttended()) {
                $reportsAttended++;
            }
        }

        return new CharacterAttendanceRowData(
            character: $mainRow->character,
            firstAttendance: $allRows->min(fn (CharacterAttendanceRowData $row) => $row->firstAttendance),
            totalReports: $totalReports,
            reportsAttended: $reportsAttended,
            percentage: $totalReports > 0 ? round($reportsAttended / $totalReports * 100, 2) : 0.0,
            attendance: $attendance,
            plannedAbsences: $mainRow->plannedAbsences,
            attendanceNames: $attendanceNames,
        );
    }
}
