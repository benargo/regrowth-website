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

class AttendanceController extends Controller
{
    public function __construct(private AttendanceMatrix $matrix) {}

    public function index(): Response
    {
        return Inertia::render('Raids/Attendance/Index');
    }

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

    private function resolveFilters(AttendanceMatrixRequest $request): AttendanceMatrixFilters
    {
        $timezone = config('app.timezone');

        $zoneIds = $request->filled('zone_ids')
            ? array_map('intval', $request->input('zone_ids'))
            : [];

        $guildTagIds = $request->filled('guild_tag_ids')
            ? array_map('intval', $request->input('guild_tag_ids'))
            : GuildTag::where('count_attendance', true)->pluck('id')->toArray();

        $sinceDate = $request->filled('since_date')
            ? Carbon::parse($request->input('since_date'), $timezone)->addDay()->setTime(5, 0, 0)->utc()
            : null;

        $beforeDate = $request->filled('before_date')
            ? Carbon::parse($request->input('before_date'), $timezone)->setTime(5, 0, 0)->utc()
            : null;

        return new AttendanceMatrixFilters(
            zoneIds: $zoneIds,
            guildTagIds: $guildTagIds,
            sinceDate: $sinceDate,
            beforeDate: $beforeDate,
        );
    }

    /**
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
