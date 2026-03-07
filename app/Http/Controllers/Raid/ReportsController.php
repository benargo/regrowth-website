<?php

namespace App\Http\Controllers\Raid;

use App\Http\Controllers\Controller;
use App\Http\Requests\Raid\ReportsIndexRequest;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    /**
     * The timezone to use for parsing date inputs.
     */
    private string $timezone;

    public function __construct()
    {
        $this->timezone = config('app.timezone', 'UTC');
    }

    /**
     * Display a paginated index of WarcraftLogs reports with optional filters.
     */
    public function index(ReportsIndexRequest $request): Response
    {
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

        $zoneIds = $request->has('zone_ids')
            ? array_map('intval', $request->input('zone_ids', []))
            : null;

        $guildTagIds = $request->has('guild_tag_ids')
            ? array_map('intval', $request->input('guild_tag_ids', []))
            : null;

        $days = $request->has('days')
            ? array_map('intval', $request->input('days', []))
            : null;

        $sinceDate = $request->filled('since_date')
            ? Carbon::parse($request->input('since_date'), $this->timezone)->startOfDay()->utc()
            : null;

        $beforeDate = $request->filled('before_date')
            ? Carbon::parse($request->input('before_date'), $this->timezone)->endOfDay()->utc()
            : null;

        $tz = $this->timezone;

        return Inertia::render('Raids/Reports/Index', [
            'zones' => $zones,
            'guildTags' => $guildTags,
            'filters' => [
                'zone_ids' => $zoneIds,
                'guild_tag_ids' => $guildTagIds,
                'days' => $days,
                'since_date' => $request->input('since_date'),
                'before_date' => $request->input('before_date'),
            ],
            'earliestDate' => $earliestDate
                ? Carbon::parse($earliestDate, 'UTC')->timezone($this->timezone)->subDay()->toDateString()
                : null,
            'reports' => Inertia::defer(function () use ($zoneIds, $guildTagIds, $days, $sinceDate, $beforeDate, $tz) {
                return Report::query()
                    ->with('guildTag')
                    ->when($zoneIds !== null && count($zoneIds) > 0, fn ($q) => $q->whereIn('zone_id', $zoneIds))
                    ->when($guildTagIds !== null && count($guildTagIds) > 0, fn ($q) => $q->whereIn('guild_tag_id', $guildTagIds))
                    ->when($days !== null && count($days) > 0, function ($q) use ($days) {
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
                    ->through(fn ($report) => [
                        'code' => $report->code,
                        'title' => $report->title,
                        'date' => $report->start_time->timezone($tz)->format('D, M j, Y'),
                        'day_of_week' => $report->start_time->timezone($tz)->format('l'),
                        'zone_name' => $report->zone_name,
                        'guild_tag' => $report->guildTag ? ['name' => $report->guildTag->name] : null,
                        'duration_minutes' => (int) $report->start_time->diffInMinutes($report->end_time),
                    ]);
            }),
        ]);
    }
}
