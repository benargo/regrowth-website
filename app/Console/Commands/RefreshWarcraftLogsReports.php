<?php

namespace App\Console\Commands;

use App\Jobs\FetchGuildRoster;
use App\Jobs\FetchWarcraftLogsAttendanceData;
use App\Jobs\FetchWarcraftLogsReportsByGuildTag;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class RefreshWarcraftLogsReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:refresh-warcraft-logs-reports {--latest}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs a batch of jobs to refresh Warcraft Logs reports for all guild tags that count towards attendance.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $since = null;

        if ($this->option('latest')) {
            $latestReport = Report::latest()->first();
            $since = $latestReport?->end_time?->addSecond();
        }

        $guildTags = GuildTag::where('count_attendance', true)->get();

        $jobs = $guildTags->map(fn ($guildTag) => new FetchWarcraftLogsReportsByGuildTag($guildTag, $since));

        $jobs->push(new FetchGuildRoster);

        $jobs->push(new FetchWarcraftLogsAttendanceData($guildTags, $since));

        Bus::batch($jobs->toArray())->before(function (Batch $batch) {
            Log::info('Starting batch to refresh Warcraft Logs reports.');
        })->progress(function (Batch $batch) {
            Log::info('Batch progress: '.$batch->progress().'% ('.$batch->processedJobs().'/'.$batch->totalJobs.')');
        })->catch(function (Batch $batch, \Throwable $e) {
            Log::error('Batch failed with error: '.$e->getMessage());
        })->then(function (Batch $batch) {
            Log::info('Batch completed successfully. Refreshed reports.');
        })->dispatch();
    }
}
