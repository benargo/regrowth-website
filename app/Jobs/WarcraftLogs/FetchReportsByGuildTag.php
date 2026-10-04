<?php

namespace App\Jobs\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Data\Reports\ReportData;
use App\Http\Integrations\WarcraftLogs\Requests\GetReportsRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsConnector;
use App\Jobs\WarcraftLogs\Concerns\ReleasesOnRateLimit;
use App\Models\Report as ReportModel;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Zone;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;

#[MaxExceptions(1)]
#[FailOnTimeout]
class FetchReportsByGuildTag implements ShouldQueue
{
    use Batchable, Queueable, ReleasesOnRateLimit;

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public GuildTag $guildTag,
        public ?Carbon $since = null,
        public ?Carbon $before = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WarcraftLogsConnector $warcraftLogs): void
    {
        $namespace = $this->guildTag->phase?->gameVersion?->warcraftlogs_namespace;

        if ($namespace === null) {
            Log::warning("Skipping reports for guild tag {$this->guildTag->id}: no game version with a Warcraft Logs namespace.");

            return;
        }

        $paginator = $warcraftLogs->paginate(new GetReportsRequest(
            $this->guildTag->id,
            $namespace,
            $this->since,
            $this->before,
        ));

        try {
            foreach ($paginator->items() as $report) {
                $this->persistReport($report);
            }
        } catch (RateLimitReachedException $exception) {
            $this->releaseUntilPointsReset($exception, "reports for guild tag {$this->guildTag->id}");

            return;
        }

        $this->syncReportLinks();
    }

    private function persistReport(ReportData $report): void
    {
        Log::info("Processing report {$report->code} ({$report->title}) for guild tag {$this->guildTag->name}.");

        if ($report->zone !== null) {
            Zone::updateOrCreate(
                ['id' => $report->zone->id],
                [
                    'name' => $report->zone->name,
                    'difficulties' => $report->zone->difficulties,
                    'expansion' => $report->zone->expansion,
                ],
            );
        }

        $guildTag = $this->resolveGuildTag($report);

        if ($guildTag === null) {
            Log::info("Report {$report->code} has no stored guild tag; saving it without one.");
        }

        ReportModel::updateOrCreate(
            ['code' => $report->code],
            [
                'title' => $report->title,
                'start_time' => $report->startTime,
                'end_time' => $report->endTime,
                'zone_id' => $report->zone?->id,
                'guild_tag_id' => $guildTag?->id,
            ],
        );
    }

    /**
     * Reuse the job's own tag when the report names it (the usual case, since the
     * request filters by guildTagID). Only look up a different tag by ID.
     */
    private function resolveGuildTag(ReportData $report): ?GuildTag
    {
        if ($report->guildTag === null) {
            return null;
        }

        if ($report->guildTag->id === $this->guildTag->id) {
            return $this->guildTag;
        }

        return GuildTag::find($report->guildTag->id);
    }

    /**
     * Synchronise auto-links for all reports belonging to this guild tag.
     *
     * Reports that fall within the same raid day (05:00–04:59 in the application timezone)
     * are linked together. Existing manual links (created_by IS NOT NULL) are never touched.
     * Stale auto-links are removed and missing auto-links are inserted.
     */
    protected function syncReportLinks(): void
    {
        $allReports = ReportModel::where('guild_tag_id', $this->guildTag->id)
            ->select('id', 'start_time')
            ->get();

        if ($allReports->isEmpty()) {
            return;
        }

        $allIds = $allReports->pluck('id')->all();

        /** @var Collection<string, Collection<int, ReportModel>> $groups */
        $groups = $allReports->groupBy(
            fn (ReportModel $report) => $report->start_time
                ->copy()
                ->setTimezone(config('app.timezone'))
                ->subHours(5)
                ->toDateString()
        );

        // Compute all desired auto-link ordered pairs (bidirectional).
        /** @var array<string, array{0: string, 1: string}> $desiredPairs */
        $desiredPairs = [];
        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $ids = $group->pluck('id')->all();
            foreach ($ids as $id1) {
                foreach ($ids as $id2) {
                    if ($id1 !== $id2) {
                        $desiredPairs["$id1|$id2"] = [$id1, $id2];
                    }
                }
            }
        }

        // Fetch all existing links where report_1 belongs to this guild tag's reports.
        $existingLinks = DB::table('pivot_report_links')
            ->whereIn('report_1', $allIds)
            ->get();

        $existingAutoPairs = $existingLinks
            ->whereNull('created_by')
            ->mapWithKeys(fn ($row) => ["$row->report_1|$row->report_2" => [$row->report_1, $row->report_2]]);

        $existingPairKeys = $existingLinks
            ->mapWithKeys(fn ($row) => ["$row->report_1|$row->report_2" => true]);

        // Delete stale auto-links no longer in the desired set.
        $staleKeys = $existingAutoPairs->keys()->filter(fn ($key) => ! isset($desiredPairs[$key]));
        $affectedIds = collect();

        foreach ($staleKeys as $key) {
            [$id1, $id2] = $existingAutoPairs[$key];
            DB::table('pivot_report_links')
                ->where('report_1', $id1)
                ->where('report_2', $id2)
                ->whereNull('created_by')
                ->delete();
            $affectedIds->push($id1, $id2);
        }

        // Insert new auto-links that don't exist at all yet.
        $toInsert = collect($desiredPairs)
            ->filter(fn ($pair, $key) => ! isset($existingPairKeys[$key]))
            ->map(fn ($pair) => [
                'report_1' => $pair[0],
                'report_2' => $pair[1],
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if (! empty($toInsert)) {
            DB::table('pivot_report_links')->insert($toInsert);
            foreach ($toInsert as $row) {
                $affectedIds->push($row['report_1'], $row['report_2']);
            }
        }

        if ($affectedIds->isNotEmpty()) {
            ReportModel::whereIn('id', $affectedIds->unique()->values()->all())->touch();
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        $tags = ['warcraftlogs', 'reports', "guild-tag:{$this->guildTag->id}"];

        $gameVersionId = $this->guildTag->phase?->game_version_id;

        if ($gameVersionId === null) {
            return $tags;
        }

        return [...$tags, "game-version:{$gameVersionId}"];
    }
}
