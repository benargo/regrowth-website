<?php

namespace App\Jobs\WarcraftLogs;

use App\Models\GuildTag;
use App\Models\Raids\Report as ReportModel;
use App\Models\Zone;
use App\Services\WarcraftLogs\Reports;
use App\Services\WarcraftLogs\ValueObjects\ReportData;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchReportsByGuildTag implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * The timezone to use when determining raid day boundaries for auto-linking reports.
     *
     * @var string
     */
    private $timezone = 'UTC';

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
    ) {
        $this->timezone = config('app.timezone');
    }

    /**
     * Execute the job.
     */
    public function handle(Reports $reportsService): void
    {
        $reports = $reportsService
            ->byGuildTags(collect([$this->guildTag]))
            ->startTime($this->since)
            ->endTime($this->before)
            ->get();

        $reports->each(function (ReportData $report) {
            Log::info('Processing report '.$report->code.' ('.$report->title.') for guild tag '.$report->guildTag?->name.'.');

            if ($report->zone !== null) {
                Zone::updateOrCreate(
                    ['id' => $report->zone->id],
                    [
                        'name' => $report->zone->name,
                        'difficulties' => $report->zone->difficulties,
                        'expansion' => $report->zone->expansion,
                    ]
                );
            }

            $reportModel = ReportModel::updateOrCreate(
                ['code' => $report->code],
                [
                    'title' => $report->title,
                    'start_time' => $report->startTime,
                    'end_time' => $report->endTime,
                    'zone_id' => $report->zone?->id,
                ],
            );

            if ($report->guildTag instanceof GuildTag) {
                Log::info('Associating report '.$report->code.' with guild tag '.$report->guildTag->name.'.');
                $reportModel->guildTag()->associate($report->guildTag);
                $reportModel->save();
            } else {
                // If the report doesn't have a guild tag, ensure it's not associated with any
                Log::info('Dissociating report '.$report->code.' from any guild tag since it has none.');
                $reportModel->guildTag()->dissociate();
                $reportModel->save();
            }
        });

        $this->syncReportLinks();
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
                ->setTimezone($this->timezone)
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
        $existingLinks = DB::table('raid_report_links')
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
            DB::table('raid_report_links')
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
            DB::table('raid_report_links')->insert($toInsert);
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
        return ['warcraftlogs', 'reports', 'guild-tag:'.$this->guildTag->id];
    }
}
