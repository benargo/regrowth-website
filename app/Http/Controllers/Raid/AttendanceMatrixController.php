<?php

namespace App\Http\Controllers\Raid;

use App\Http\Controllers\Controller;
use App\Http\Requests\Raid\AttendanceMatrixRequest;
use App\Models\GuildRank;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use App\Services\AttendanceCalculator\AttendanceMatrix;
use App\Services\AttendanceCalculator\AttendanceMatrixFilters;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceMatrixController extends Controller
{
    /**
     * The service responsible for calculating attendance matrices based on the provided filters. This service should be designed to efficiently handle the potentially complex queries and calculations required to generate the attendance data for raids.
     */
    private AttendanceMatrix $matrix;

    /**
     * The timezone to use for parsing date inputs. This should be the same as the timezone used in the application configuration.
     */
    private string $timezone;

    /**
     * AttendanceMatrixController constructor.
     */
    public function __construct(AttendanceMatrix $matrix)
    {
        $this->matrix = $matrix;
        $this->timezone = config('app.timezone', 'UTC');
    }

    /**
     * Display the attendance matrix based on the provided filters. This method should accept an AttendanceMatrixRequest, which contains the necessary parameters for filtering the attendance data. The method should then use the AttendanceMatrix service to calculate the attendance data based on the filters and return an Inertia response that renders the view for the attendance matrix, passing the calculated data to the view.
     */
    public function matrix(AttendanceMatrixRequest $request): Response
    {
        $filters = $this->resolveFilters($request);

        return Inertia::render('Raids/Attendance/Matrix', [
            'ranks' => GuildRank::where('count_attendance', true)->orderBy('position')->get(),
            'zones' => Report::select('zone_id', 'zone_name')->distinct()->get()->map(fn ($r) => ['id' => $r->zone_id, 'name' => $r->zone_name])->sortBy('name')->values(),
            'guildTags' => GuildTag::orderBy('name')->get(),
            'filters' => $this->serializeFilters($filters, $request),
            'matrix' => Inertia::defer(fn () => $this->matrix->matrixWithFilters($filters)),
        ]);
    }

    /**
     * Resolve the filters from the request and return an instance of AttendanceMatrixFilters. This method should handle parsing the input parameters from the request, such as zone IDs, guild tag IDs, and date ranges, and convert them into the appropriate format for use in the AttendanceMatrix service. The method should also handle any necessary validation or default values for the filters.
     */
    private function resolveFilters(AttendanceMatrixRequest $request): AttendanceMatrixFilters
    {
        $zoneIds = $request->filled('zone_ids')
            ? array_map('intval', $request->input('zone_ids'))
            : [];

        $guildTagIds = $request->filled('guild_tag_ids')
            ? array_map('intval', $request->input('guild_tag_ids'))
            : GuildTag::where('count_attendance', true)->pluck('id')->toArray();

        $sinceDate = $request->filled('since_date')
            ? Carbon::parse($request->input('since_date'), $this->timezone)->addDay()->setTime(5, 0, 0)->utc()
            : null;

        $beforeDate = $request->filled('before_date')
            ? Carbon::parse($request->input('before_date'), $this->timezone)->setTime(5, 0, 0)->utc()
            : null;

        return new AttendanceMatrixFilters(
            zoneIds: $zoneIds,
            guildTagIds: $guildTagIds,
            sinceDate: $sinceDate,
            beforeDate: $beforeDate,
        );
    }

    /**
     * Serialize the filters into a format suitable for passing to the view. This method should take the AttendanceMatrixFilters instance and convert it into an array format that can be easily used in the Inertia response. The serialized filters should include the zone IDs, guild tag IDs, and date ranges in a format that can be easily consumed by the frontend components.
     * 
     * @return array{zone_ids: array<int, int>, guild_tag_ids: array<int, int>, since_date: string|null, before_date: string|null}
     */
    private function serializeFilters(AttendanceMatrixFilters $filters, AttendanceMatrixRequest $request): array
    {
        return [
            'zone_ids' => $filters->zoneIds,
            'guild_tag_ids' => $filters->guildTagIds,
            'since_date' => $request->input('since_date'),
            'before_date' => $request->input('before_date'),
        ];
    }
}
