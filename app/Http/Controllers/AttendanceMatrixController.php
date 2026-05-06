<?php

namespace App\Http\Controllers;

use App\Http\Requests\Raid\AttendanceMatrixRequest;
use App\Http\Resources\PlannedAbsenceResource;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\GuildTag;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use App\Models\Zone;
use App\Services\Attendance\AttendanceMatrixData;
use App\Services\Attendance\Calculator;
use App\Services\Attendance\CharacterAttendanceRowData;
use App\Services\Attendance\DataTable;
use App\Services\Attendance\FiltersData;
use App\Services\Attendance\Matrix;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceMatrixController extends Controller
{
    public function __construct(
        protected readonly Calculator $calculator
    ) {}

    /**
     * Display the attendance matrix based on the provided filters.
     */
    public function __invoke(AttendanceMatrixRequest $request): Response
    {
        $filters = $request->filters();

        return Inertia::render('Raiding/Attendance/Matrix', [
            'ranks' => GuildRank::orderBy('position')->get(),
            'zones' => Zone::whereIn('id', Report::select('zone_id')->whereNotNull('zone_id')->distinct())->orderBy('name')->get(),
            'guildTags' => GuildTag::orderBy('name')->get(),
            'filters' => $filters,
            'earliestDate' => $request->resolveMinDate(),
            'matrix' => Inertia::defer(fn () => Cache::tags(['attendance'])->remember(
                $filters->cacheKey('attendance:matrix:'),
                now()->addHours(24),
                function () use ($filters) {
                    $matrixData = $this->matrixWithFilters($filters);
                    $referencedAbsences = new Collection;

                    foreach ($matrixData->rows as $row) {
                        /** @var CharacterAttendanceRowData $row */
                        foreach ($row->plannedAbsences as $absence) {
                            if ($absence instanceof PlannedAbsence) {
                                $referencedAbsences->put($absence->id, $absence);
                            }
                        }
                    }

                    return array_merge($matrixData->toArray(), [
                        'planned_absences' => PlannedAbsenceResource::collection($referencedAbsences->values())->resolve(),
                    ]);
                },
            )),
        ]);
    }

    /**
     * Build the attendance matrix with the given server-side filters applied.
     */
    private function matrixWithFilters(FiltersData $filters): AttendanceMatrixData
    {
        $table = new DataTable($this->calculator, $filters);
        $raids = $table->columns();
        $rows = $table->rows();

        if ($filters->includeLinkedCharacters && $rows->isNotEmpty()) {
            $rows = $this->mergeLinkedCharacters($rows, $table->resolvedRankIds(), $raids->count());
        }

        if ($filters->character !== null) {
            $characterId = $filters->character->id;
            $rows = $rows->filter(fn (CharacterAttendanceRowData $row) => $row->character->id === $characterId)->values();
        }

        return new AttendanceMatrixData($raids, $rows);
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
}
