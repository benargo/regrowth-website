<?php

namespace App\Http\Controllers\Raid;

use App\Http\Controllers\Controller;
use App\Http\Requests\Raid\ReportsIndexRequest;
use App\Http\Resources\WarcraftLogs\ReportResource;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use Carbon\Carbon;
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
}
