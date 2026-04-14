<?php

namespace App\Console\Commands;

use App\Jobs\FetchWarcraftLogsAttendanceData;
use App\Jobs\FetchWarcraftLogsReportsByGuildTag;
use App\Models\Raids\Report;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshWarcraftLogsReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:refresh-warcraft-logs-reports {--latest} {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs a batch of jobs to refresh Warcraft Logs reports for all guild tags that count towards attendance.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $since = null;

        if ($this->option('latest')) {
            $latestReport = Report::latest()->first();
            $since = $latestReport?->end_time?->addSecond();
        }

        if ($this->option('all')) {
            $guildTags = GuildTag::all();
        } else {
            $guildTags = GuildTag::where('count_attendance', true)->get();
        }

        $jobs = $guildTags->map(fn ($guildTag) => new FetchWarcraftLogsReportsByGuildTag($guildTag, $since));

        Bus::batch($jobs->toArray())->then(function (Batch $batch) {
            Log::info('Batch completed successfully. Refreshed reports.');
            Log::info('Starting to fetch attendance data.');
            dispatch(new FetchWarcraftLogsAttendanceData);
        })->catch(function (Throwable $e) {
            Log::error('Batch failed with error: '.$e->getMessage());
        })->dispatch();
    }
}
