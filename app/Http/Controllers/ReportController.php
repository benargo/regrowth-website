<?php

namespace App\Http\Controllers;

use App\Http\Requests\Raid\ReportsIndexRequest;
use App\Http\Requests\Raid\StoreReportRequest;
use App\Http\Requests\Raid\UpdateReportRequest;
use App\Http\Resources\WarcraftLogs\LinkedReportResource;
use App\Http\Resources\WarcraftLogs\ReportClusterResource;
use App\Http\Resources\WarcraftLogs\ReportResource;
use App\Models\Character;
use App\Models\GuildTag;
use App\Models\Raids\Report;
use App\Models\Raids\ReportLink;
use App\Models\User;
use App\Models\Zone;
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
    // ============ Index ============

    /**
     * Display a paginated index of WarcraftLogs reports with optional filters.
     */
    public function index(ReportsIndexRequest $request): Response
    {
        $timezone = config('app.timezone');
        $zones = Zone::whereIn('id', Report::select('zone_id')->whereNotNull('zone_id')->distinct())
            ->orderBy('name')
            ->get();

        $guildTags = GuildTag::orderBy('name')->get();

        $zoneIds = $request->zoneIds();
        $guildTagIds = $request->guildTagIds();
        $days = $request->days();

        $sinceDate = $request->filled('since_date')
            ? Carbon::parse($request->input('since_date'), $timezone)->startOfDay()->utc()
            : null;

        $beforeDate = $request->filled('before_date')
            ? Carbon::parse($request->input('before_date'), $timezone)->endOfDay()->utc()
            : null;

        return Inertia::render('Raiding/Reports/Index', [
            'zones' => $zones,
            'guildTags' => $guildTags,
            'filters' => [
                'zone_ids' => $request->input('zone_ids'),
                'guild_tag_ids' => $request->input('guild_tag_ids'),
                'days' => $request->input('days'),
                'since_date' => $request->input('since_date'),
                'before_date' => $request->input('before_date'),
            ],
            'earliestDate' => $request->resolveMinDate(),
            'reports' => Inertia::defer(function () use ($zoneIds, $guildTagIds, $days, $sinceDate, $beforeDate) {
                return ReportResource::collection(
                    Report::query()
                        ->with(['guildTag', 'zone'])
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
                        ->withQueryString()
                );
            }),
        ]);
    }

    // ============ Show ============

    /**
     * Display the details of a single WarcraftLogs report.
     */
    public function show(Request $request, Report $report): Response
    {
        $report->load(['guildTag', 'zone', 'characters.rank', 'linkedReports']);

        return Inertia::render('Raiding/Reports/Show', [
            'report' => new ReportResource($report),
            'canManageLinks' => $request->user()->can('update', $report),
            'lootCouncillorCandidates' => Inertia::optional(function () use ($report) {
                $linkedReportIds = $report->linkedReports->pluck('id');

                $excludedIds = Character::select('characters.id')
                    ->join('pivot_characters_raid_reports', 'characters.id', '=', 'pivot_characters_raid_reports.character_id')
                    ->where('pivot_characters_raid_reports.is_loot_councillor', true)
                    ->whereIn('pivot_characters_raid_reports.raid_report_id', $linkedReportIds->push($report->id))
                    ->pluck('characters.id');

                return Character::select('id', 'name', 'playable_class', 'is_main')
                    ->where('is_loot_councillor', true)
                    ->whereNotIn('id', $excludedIds)
                    ->orderBy('name')
                    ->get();
            }),
            'impactedReports' => Inertia::optional(function () use ($report) {
                return LinkedReportResource::collection(
                    $report->linkedReports()->wherePivotNotNull('created_by')->get()
                );
            }),
            'nearbyReports' => Inertia::optional(
                fn () => ReportClusterResource::collection($this->buildNearbyReportClusters($request))
            ),
        ]);
    }

    // ============ Create, Store, and Update ============

    /**
     * Display the form to manually create a new raid report.
     */
    public function create(Request $request): Response
    {
        $zones = Zone::orderBy('name')->get();

        $expansions = $zones
            ->groupBy(fn (Zone $zone) => $zone->expansion->id)
            ->map(fn ($groupedZones, $expansionId) => [
                'id' => $groupedZones->first()->expansion->id,
                'name' => $groupedZones->first()->expansion->name,
                'zones' => $groupedZones->map(fn (Zone $zone) => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('Raiding/Reports/Create', [
            'expansions' => $expansions,
            'defaultExpansionId' => config('services.warcraftlogs.expansion_id'),
            'guildTags' => GuildTag::orderBy('name')->get(),
            'characters' => Character::select('id', 'name', 'playable_class', 'is_main')->orderBy('name')->get(),
            'lootCouncillorCandidates' => Inertia::optional(function () {
                return Character::hydrate(
                    Cache::tags('characters')->remember(
                        'characters:loot_councillors',
                        now()->addDay(),
                        function () {
                            return Character::select('id', 'name', 'playable_class', 'is_main')
                                ->where('is_loot_councillor', true)
                                ->orderBy('name')
                                ->get()
                                ->toArray();
                        }
                    )
                );
            }),
            'nearbyReports' => Inertia::optional(
                fn () => ReportClusterResource::collection($this->buildNearbyReportClusters($request))
            ),
        ]);
    }

    /**
     * Store a manually created raid report.
     */
    public function store(StoreReportRequest $request): RedirectResponse
    {
        $report = Report::create([
            'title' => $request->title,
            'start_time' => Carbon::parse($request->start_time, 'Europe/Paris')->utc(),
            'end_time' => Carbon::parse($request->end_time, 'Europe/Paris')->utc(),
            'guild_tag_id' => $request->guild_tag_id,
            'zone_id' => $request->zone_id,
        ]);

        if (! empty($request->character_ids)) {
            $report->characters()->attach(
                collect($request->character_ids)
                    ->mapWithKeys(fn ($id) => [$id => ['presence' => 1]])
                    ->all()
            );
        }

        if (! empty($request->linked_report_ids)) {
            $this->createReportLinks($report, $request->linked_report_ids, $request->user());
        }

        if (! empty($request->loot_councillor_ids)) {
            $this->attachLootCouncillors($report, $request->loot_councillor_ids);
        }

        return redirect()->route('raiding.reports.show', $report)->with('success', 'New report created');
    }

    /**
     * Update a raid report.
     *
     * Manages bidirectional linked reports via the `links` payload:
     *   - `action: create` → create links between this report and all reports in `link_ids`.
     *   - `action: delete` → remove all manually created links for this report.
     */
    public function update(UpdateReportRequest $request, Report $report): RedirectResponse
    {
        $linksAction = $request->input('links.action');

        if ($linksAction === 'delete') {
            $existingLinkedIds = $report->linkedReports()
                ->wherePivotNotNull('created_at')
                ->pluck('id')
                ->all();

            if (! empty($existingLinkedIds)) {
                // Delete forward direction: report → linked
                $report->linkedReports()->detach($existingLinkedIds);

                // Delete reverse direction: linked → report (manual links only)
                DB::table('raid_report_links')
                    ->whereIn('report_1', $existingLinkedIds)
                    ->where('report_2', $report->id)
                    ->whereNotNull('created_at')
                    ->delete();
            }
        } elseif ($linksAction === 'create') {
            $this->createReportLinks($report, $request->input('links.link_ids', []), $request->user());
        }

        $lootCouncillorsAction = $request->input('loot_councillors.action');

        if ($lootCouncillorsAction === 'create') {
            $this->attachLootCouncillors($report, $request->input('loot_councillors.character_ids', []));
        } elseif ($lootCouncillorsAction === 'delete') {
            $characterIds = $request->input('loot_councillors.character_ids', []);

            foreach ($characterIds as $characterId) {
                $pivot = $report->characters()->wherePivot('character_id', $characterId)->first()?->pivot;

                if ($pivot && $pivot->presence === 0) {
                    $report->characters()->detach($characterId);
                } else {
                    $report->characters()->updateExistingPivot($characterId, ['is_loot_councillor' => false]);
                }
            }
        }

        $report->touch();

        return back();
    }

    // ============= Helpers =============

    /**
     * Create bidirectional links between the given report and all selected reports,
     * expanding the set to include reports already linked to the selected ones.
     *
     * @param  array<int, string>  $selectedIds
     */
    private function createReportLinks(Report $report, array $selectedIds, User $user): void
    {
        $selectedReports = Report::whereIn('id', $selectedIds)->with('linkedReports')->get();

        $transitiveReports = $selectedReports
            ->flatMap(fn (Report $r) => $r->linkedReports)
            ->unique('id')
            ->reject(fn (Report $r) => $r->id === $report->id);

        $allReports = $selectedReports->merge($transitiveReports)->push($report)->unique('id');

        foreach ($allReports as $r1) {
            $r1->load('linkedReports');

            $existingIds = $r1->linkedReports->pluck('id')->all();

            $newIds = $allReports
                ->where('id', '!=', $r1->id)
                ->pluck('id')
                ->diff($existingIds);

            $pivotData = $newIds
                ->mapWithKeys(fn ($id) => [$id => ['created_by' => $user->getKey()]])
                ->all();

            if (! empty($pivotData)) {
                $r1->linkedReports()->attach($pivotData);
            }
        }
    }

    /**
     * Build a paginated collection of report clusters for the "Link Reports" modal.
     *
     * Uses Union-Find over all reports and their links to group them into transitive
     * clusters, then paginates the resulting clusters at 5 per page.
     */
    private function buildNearbyReportClusters(Request $request): LengthAwarePaginator
    {
        $page = max(1, $request->integer('nearby', 1));
        $perPage = 5;

        $reportTimes = Report::hydrate(
            Cache::tags(['raiding', 'warcraftlogs'])->remember(
                'reports:select:id,start_time',
                now()->addMinutes(5),
                fn () => Report::select('id', 'start_time')->get()->toArray()
            )
        )->pluck('start_time', 'id');

        $links = ReportLink::hydrate(
            Cache::tags(['raiding', 'warcraftlogs'])->remember(
                'reports:links:all_edges',
                now()->addMinutes(5),
                fn () => DB::table('raid_report_links')->select('report_1', 'report_2')->get()->toArray()
            )
        );

        $allIds = $reportTimes->keys()->all();
        $parent = array_combine($allIds, $allIds);

        $find = function (string $id) use (&$parent): string {
            $root = $id;
            while ($parent[$root] !== $root) {
                $root = $parent[$root];
            }
            while ($parent[$id] !== $root) {
                $next = $parent[$id];
                $parent[$id] = $root;
                $id = $next;
            }

            return $root;
        };

        foreach ($links as $link) {
            if (! isset($parent[$link->report_1]) || ! isset($parent[$link->report_2])) {
                continue;
            }

            $rootA = $find($link->report_1);
            $rootB = $find($link->report_2);

            if ($rootA !== $rootB) {
                $parent[$rootB] = $rootA;
            }
        }

        $clusterMap = [];
        foreach ($allIds as $id) {
            $clusterMap[$find($id)][] = $id;
        }

        $sortedClusters = collect($clusterMap)
            ->map(fn (array $ids) => collect($ids)->sortByDesc(fn ($id) => $reportTimes[$id])->values()->all())
            ->sortByDesc(fn (array $ids) => $reportTimes[$ids[0]])
            ->values();

        $total = $sortedClusters->count();
        $pageItems = $sortedClusters->slice(($page - 1) * $perPage, $perPage)->values();

        $pageReports = Report::whereIn('id', $pageItems->flatten()->all())
            ->with('zone')
            ->get()
            ->keyBy('id');

        $clusters = $pageItems->map(fn (array $ids) => [
            'id' => $ids[0],
            'reports' => collect($ids)->map(fn ($id) => $pageReports[$id])->filter()->values(),
        ]);

        return new LengthAwarePaginator($clusters, $total, $perPage, $page, [
            'path' => $request->url(),
            'pageName' => 'nearby',
        ]);
    }

    /**
     * Attach or update loot councillor status for the given characters on a report.
     *
     * @param  array<int, int>  $characterIds
     */
    private function attachLootCouncillors(Report $report, array $characterIds): void
    {
        $existingIds = $report->characters()->pluck('characters.id')->toArray();

        foreach ($characterIds as $characterId) {
            if (in_array($characterId, $existingIds)) {
                $report->characters()->updateExistingPivot($characterId, ['is_loot_councillor' => true]);
            } else {
                $report->characters()->attach($characterId, ['presence' => 0, 'is_loot_councillor' => true]);
            }
        }
    }
}
