<?php

namespace App\Console\Commands;

use App\Jobs\WarcraftLogs\FetchAttendanceData;
use App\Jobs\WarcraftLogs\FetchGuildTags;
use App\Jobs\WarcraftLogs\FetchReportsByGuildTag;
use App\Models\Report;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Bus\Batch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('fetch:warcraft-logs {--latest} {--all}')]
#[Description('Runs a batch of jobs to refresh Warcraft Logs reports for all guild tags that count towards attendance.')]
class FetchWarcraftLogs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        dispatch_sync(new FetchGuildTags);

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

        $jobs = $guildTags->map(fn ($guildTag) => new FetchReportsByGuildTag($guildTag, $since));

        Bus::batch($jobs->toArray())->then(function (Batch $batch) {
            Log::info('Batch completed successfully. Refreshed reports.');
            Log::info('Starting to fetch attendance data.');
            dispatch(new FetchAttendanceData);
        })->catch(function (Throwable $e) {
            Log::error('Batch failed with error: '.$e->getMessage());
        })->dispatch();
    }
}
