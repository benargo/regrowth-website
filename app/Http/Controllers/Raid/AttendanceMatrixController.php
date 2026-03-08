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
use Illuminate\Support\Facades\Cache;
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

        $earliestDate = Cache::tags('warcraftlogs')->remember(
            'attendance_matrix_earliest_date',
            now()->addDays(7),
            fn () => Report::min('start_time'),
        );

        return Inertia::render('Raids/Attendance/Matrix', [
            'ranks' => GuildRank::orderBy('position')->get(),
            'zones' => Report::select('zone_id', 'zone_name')->whereNotNull('zone_id')->distinct()->get()->map(fn ($r) => ['id' => $r->zone_id, 'name' => $r->zone_name])->sortBy('name')->values(),
            'guildTags' => GuildTag::orderBy('name')->get(),
            'filters' => $this->serializeFilters($filters, $request),
            'earliestDate' => $earliestDate
                ? Carbon::parse($earliestDate, 'UTC')->timezone($this->timezone)->subDay()->toDateString()
                : null,
            'matrix' => Inertia::defer(function () use ($filters) {
                $cacheKey = $this->matrixCacheKey($filters);

                return Cache::tags(['attendance', 'attendance:matrix'])
                    ->remember($cacheKey, now()->addHours(24), function () use ($filters) {
                        $matrix = $this->matrix->matrixWithFilters($filters);

                        return method_exists($matrix, 'toArray') ? $matrix->toArray() : $matrix;
                    });
            }),
        ]);
    }

    /**
     * Resolve the filters from the request and return an instance of AttendanceMatrixFilters. This method should handle parsing the input parameters from the request, such as zone IDs, guild tag IDs, and date ranges, and convert them into the appropriate format for use in the AttendanceMatrix service. The method should also handle any necessary validation or default values for the filters.
     */
    private function resolveFilters(AttendanceMatrixRequest $request): AttendanceMatrixFilters
    {
        $rankIds = $request->rankIds() ?? [];
        $zoneIds = $request->zoneIds();
        $guildTagIds = $request->guildTagIds() ?? GuildTag::where('count_attendance', true)->pluck('id')->toArray();

        $sinceDate = $request->filled('since_date')
            ? Carbon::parse($request->input('since_date'), $this->timezone)->addDay()->setTime(5, 0, 0)->utc()
            : null;

        $beforeDate = $request->filled('before_date')
            ? Carbon::parse($request->input('before_date'), $this->timezone)->setTime(5, 0, 0)->utc()
            : null;

        return new AttendanceMatrixFilters(
            rankIds: $rankIds,
            zoneIds: $zoneIds,
            guildTagIds: $guildTagIds,
            sinceDate: $sinceDate,
            beforeDate: $beforeDate,
            includeLinkedCharacters: $request->combineLinkedCharacters(),
        );
    }

    /**
     * Build a deterministic cache key for the given filters.
     *
     * Arrays are sorted before hashing so `[1, 2]` and `[2, 1]` produce the same key.
     */
    private function matrixCacheKey(AttendanceMatrixFilters $filters): string
    {
        $zoneIds = $filters->zoneIds;

        if ($zoneIds !== null) {
            sort($zoneIds);
        }

        $guildTagIds = $filters->guildTagIds;
        sort($guildTagIds);

        $rankIds = $filters->rankIds;
        sort($rankIds);

        $payload = [
            'zone_ids' => $zoneIds,
            'guild_tag_ids' => $guildTagIds,
            'rank_ids' => $rankIds,
            'since_date' => $filters->sinceDate?->toISOString(),
            'before_date' => $filters->beforeDate?->toISOString(),
            'combine_linked_characters' => $filters->includeLinkedCharacters,
        ];

        return 'attendance:matrix:'.hash('crc32', json_encode($payload));
    }

    /**
     * Serialize the filters into a format suitable for passing to the view. This method should take the AttendanceMatrixFilters instance and convert it into an array format that can be easily used in the Inertia response. The serialized filters should include the zone IDs, guild tag IDs, and date ranges in a format that can be easily consumed by the frontend components.
     *
     * @return array{rank_ids: array<int, int>, zone_ids: array<int, int>|null, guild_tag_ids: array<int, int>, since_date: string|null, before_date: string|null, combine_linked_characters: bool}
     */
    private function serializeFilters(AttendanceMatrixFilters $filters, AttendanceMatrixRequest $request): array
    {
        return [
            'rank_ids' => $request->input('rank_ids'),
            'zone_ids' => $request->input('zone_ids'),
            'guild_tag_ids' => $request->input('guild_tag_ids'),
            'since_date' => $request->input('since_date'),
            'before_date' => $request->input('before_date'),
            'combine_linked_characters' => $filters->includeLinkedCharacters,
        ];
    }
}
