<?php

namespace App\Http\Controllers;

use App\Http\Requests\Raid\AttendanceMatrixRequest;
use App\Http\Resources\AttendanceMatrixRowResource;
use App\Http\Resources\GuildRankResource;
use App\Http\Resources\GuildTagResource;
use App\Http\Resources\PlannedAbsenceResource;
use App\Http\Resources\ZoneResource;
use App\Models\GuildRank;
use App\Models\GuildTag;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use App\Models\Zone;
use App\Services\Attendance\Calculator;
use App\Services\Attendance\CharacterAttendanceRowData;
use App\Services\Attendance\DataTable;
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
            'ranks' => GuildRankResource::collection(GuildRank::orderBy('position')->get())->resolve($request),
            'zones' => ZoneResource::collection(
                Zone::whereIn('id', Report::select('zone_id')->whereNotNull('zone_id')->distinct())->orderBy('name')->get()
            )->resolve($request),
            'guildTags' => GuildTagResource::collection(GuildTag::orderBy('name')->get())->resolve($request),
            'filters' => $filters,
            'earliestDate' => $request->resolveMinDate(),
            'matrix' => Inertia::defer(fn () => Cache::tags(['attendance'])->remember(
                $filters->cacheKey('attendance:matrix:'),
                now()->addHours(24),
                function () use ($filters, $request) {
                    $table = new DataTable($this->calculator, $filters);
                    $raids = $table->columns();
                    $rows = $table->mergedRows();

                    if ($filters->character !== null) {
                        $characterId = $filters->character->id;
                        $rows = $rows->filter(fn (CharacterAttendanceRowData $row) => $row->character->id === $characterId)->values();
                    }

                    $referencedAbsences = new Collection;

                    foreach ($rows as $row) {
                        foreach ($row->plannedAbsences as $absence) {
                            if ($absence instanceof PlannedAbsence) {
                                $referencedAbsences->put($absence->id, $absence);
                            }
                        }
                    }

                    return [
                        'raids' => $raids->values()->all(),
                        'rows' => AttendanceMatrixRowResource::collection($rows)->resolve($request),
                        'planned_absences' => PlannedAbsenceResource::collection($referencedAbsences->values())->resolve(),
                    ];
                },
            )),
        ]);
    }
}
