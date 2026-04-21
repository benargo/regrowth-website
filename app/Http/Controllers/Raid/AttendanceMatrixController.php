<?php

namespace App\Http\Controllers\Raid;

use App\Http\Controllers\Controller;
use App\Http\Requests\Raid\AttendanceMatrixRequest;
use App\Http\Resources\PlannedAbsenceResource;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Zone;
use App\Services\Attendance\CharacterAttendanceRowData;
use App\Services\Attendance\Matrix;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceMatrixController extends Controller
{
    public function __construct(private readonly Matrix $matrix) {}

    /**
     * Display the attendance matrix based on the provided filters.
     */
    public function matrix(AttendanceMatrixRequest $request): Response
    {
        $filters = $request->filters();

        return Inertia::render('Raids/Attendance/Matrix', [
            'ranks' => GuildRank::orderBy('position')->get(),
            'zones' => Zone::whereIn('id', Report::select('zone_id')->whereNotNull('zone_id')->distinct())->orderBy('name')->get(),
            'guildTags' => GuildTag::orderBy('name')->get(),
            'filters' => $filters,
            'earliestDate' => $request->resolveMinDate(),
            'matrix' => Inertia::defer(fn () => Cache::tags(['attendance'])->remember(
                $filters->cacheKey('attendance:matrix:'),
                now()->addHours(24),
                function () use ($filters) {
                    $matrixData = $this->matrix->matrixWithFilters($filters);
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
}
