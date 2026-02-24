<?php

namespace App\Jobs;

use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report as ReportModel;
use App\Services\WarcraftLogs\Data\Report;
use App\Services\WarcraftLogs\Reports;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Log;

class FetchWarcraftLogsReportsByGuildTag implements ShouldQueue
{
    use Batchable, Queueable;

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
    public function handle(Reports $reportsService): void
    {
        $reports = $reportsService
            ->byGuildTags(collect([$this->guildTag]))
            ->startTime($this->since)
            ->endTime($this->before)
            ->get();

        $reports->each(function (Report $report) {
            Log::info('Processing report '.$report->code.' ('.$report->title.') for guild tag '.$report->guildTag?->name.'.');

            $reportModel = ReportModel::updateOrCreate(
                ['code' => $report->code],
                [
                    'title' => $report->title,
                    'start_time' => $report->startTime,
                    'end_time' => $report->endTime,
                    'zone_id' => $report->zone?->id,
                    'zone_name' => $report->zone?->name,
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
