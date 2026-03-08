<?php

namespace App\Http\Controllers\Raid;

use App\Http\Controllers\Controller;
use App\Http\Requests\Raid\DestroyReportLinksRequest;
use App\Http\Requests\Raid\ReportsIndexRequest;
use App\Http\Requests\Raid\StoreReportLinksRequest;
use App\Http\Resources\WarcraftLogs\LinkedReportResource;
use App\Http\Resources\WarcraftLogs\ReportResource;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display a paginated index of WarcraftLogs reports with optional filters.
     */
    public function index(ReportsIndexRequest $request): Response
    {
        $timezone = config('app.timezone', 'UTC');
        $zones = Report::select('zone_id', 'zone_name')
            ->whereNotNull('zone_id')
            ->distinct()
            ->get()
            ->map(fn ($r) => ['id' => $r->zone_id, 'name' => $r->zone_name])
            ->sortBy('name')
            ->values();

        $guildTags = GuildTag::orderBy('name')->get();

        $earliestDate = Cache::tags('warcraftlogs')->remember(
            'reports_earliest_date',
            now()->addDays(7),
            fn () => Report::min('start_time'),
        );

        $zoneIds = $request->zoneIds();
        $guildTagIds = $request->guildTagIds();
        $days = $request->days();

        $sinceDate = $request->filled('since_date')
            ? Carbon::parse($request->input('since_date'), $timezone)->startOfDay()->utc()
            : null;

        $beforeDate = $request->filled('before_date')
            ? Carbon::parse($request->input('before_date'), $timezone)->endOfDay()->utc()
            : null;

        return Inertia::render('Raids/Reports/Index', [
            'zones' => $zones,
            'guildTags' => $guildTags,
            'filters' => [
                'zone_ids' => $request->input('zone_ids'),
                'guild_tag_ids' => $request->input('guild_tag_ids'),
                'days' => $request->input('days'),
                'since_date' => $request->input('since_date'),
                'before_date' => $request->input('before_date'),
            ],
            'earliestDate' => $earliestDate
                ? Carbon::parse($earliestDate, 'UTC')->timezone($timezone)->subDay()->toDateString()
                : null,
            'reports' => Inertia::defer(function () use ($zoneIds, $guildTagIds, $days, $sinceDate, $beforeDate) {
                return ReportResource::collection(
                    Report::query()
                        ->with('guildTag')
                        ->withCount('linkedReports')
                        ->when($zoneIds !== null, fn ($q) => $q->whereIn('zone_id', $zoneIds))
                        ->when($guildTagIds !== null, fn ($q) => $q->whereIn('guild_tag_id', $guildTagIds))
                        ->when($days !== null, function ($q) use ($days) {
                            if (DB::connection()->getDriverName() === 'sqlite') {
                                // SQLite: strftime('%w') returns 0=Sun, 1=Mon, ..., 6=Sat (same as Carbon)
                                $q->whereIn(DB::raw("CAST(strftime('%w', datetime(start_time)) AS INTEGER)"), $days);
                            } else {
                                // MySQL: DAYOFWEEK returns 1=Sun, 2=Mon, ..., 7=Sat
                                $q->whereIn(DB::raw('DAYOFWEEK(start_time)'), array_map(fn ($d) => $d + 1, $days));
                            }
                        })
                        ->when($sinceDate, fn ($q) => $q->where('start_time', '>=', $sinceDate))
                        ->when($beforeDate, fn ($q) => $q->where('start_time', '<=', $beforeDate))
                        ->orderBy('start_time', 'desc')
                        ->paginate(25)
                );
            }),
        ]);
    }

    /**
     * Display the details of a single WarcraftLogs report.
     */
    public function show(Request $request, Report $report): Response
    {
        $report->load(['guildTag', 'characters.rank', 'linkedReports']);

        return Inertia::render('Raids/Reports/Show', [
            'report' => new ReportResource($report),
            'canManageLinks' => $request->user()->can('update', $report),
            'impactedReports' => Inertia::optional(function () use ($report) {
                return LinkedReportResource::collection(
                    $report->linkedReports()->wherePivotNotNull('created_by')->get()
                );
            }),
            'nearbyReports' => Inertia::optional(function () use ($request, $report) {
                $page = max(1, $request->integer('nearby_page', 1));
                $perPage = 15;

                $newerCount = Report::where('start_time', '>', $report->start_time)->count();
                $page1Offset = max(0, $newerCount - 7);
                $offset = $page1Offset + ($page - 1) * $perPage;

                $total = Report::count();
                $totalFromStart = $total - $page1Offset;

                $reports = Report::orderBy('start_time', 'desc')
                    ->skip($offset)
                    ->take($perPage)
                    ->get();

                $paginator = new LengthAwarePaginator($reports, $totalFromStart, $perPage, $page, [
                    'path' => $request->url(),
                    'pageName' => 'nearby_page',
                ]);

                return LinkedReportResource::collection($paginator);
            }),
        ]);
    }

    /**
     * Remove all manually created bidirectional links for the given report.
     */
    public function destroyLinks(DestroyReportLinksRequest $request, Report $report): RedirectResponse
    {
        $linkedCodes = $report->linkedReports()
            ->wherePivotNotNull('created_by')
            ->pluck('code')
            ->all();

        if (! empty($linkedCodes)) {
            // Delete forward direction: report → linked
            $report->linkedReports()->detach($linkedCodes);

            // Delete reverse direction: linked → report
            DB::table('pivot_wcl_reports_links')
                ->whereIn('report_1', $linkedCodes)
                ->where('report_2', $report->code)
                ->delete();
        }

        Cache::tags('warcraftlogs')->flush();

        return back();
    }

    /**
     * Create bidirectional links between the given report and all selected reports,
     * including links between the selected reports themselves.
     */
    public function storeLinks(StoreReportLinksRequest $request, Report $report): RedirectResponse
    {
        $selectedReports = Report::whereIn('code', $request->validated('codes'))->get();

        $allReports = $selectedReports->push($report);

        foreach ($allReports as $r1) {
            $r1->load('linkedReports');

            $existingCodes = $r1->linkedReports->pluck('code')->all();

            $newCodes = $allReports
                ->where('code', '!=', $r1->code)
                ->pluck('code')
                ->diff($existingCodes);

            $pivotData = $newCodes
                ->mapWithKeys(fn ($code) => [$code => ['created_by' => $request->user()->getKey()]])
                ->all();

            if (! empty($pivotData)) {
                $r1->linkedReports()->attach($pivotData);
            }
        }

        Cache::tags('warcraftlogs')->flush();

        return back();
    }
}
