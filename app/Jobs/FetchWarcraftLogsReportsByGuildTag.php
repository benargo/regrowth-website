<?php

namespace App\Jobs;

use App\Models\WarcraftLogs\GuildTag;
use App\Services\WarcraftLogs\Reports;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;

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
        $reportsService
            ->byGuildTags(collect([$this->guildTag]))
            ->startTime($this->since)
            ->endTime($this->before)
            ->toDatabase();
    }
}
