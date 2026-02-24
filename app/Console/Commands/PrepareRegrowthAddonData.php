<?php

namespace App\Console\Commands;

use App\Jobs\FetchGuildRoster;
use App\Jobs\FetchWarcraftLogsAttendanceData;
use App\Jobs\FetchWarcraftLogsReportsByGuildTag;
use App\Jobs\RegrowthAddon\Export\BuildCouncillors;
use App\Jobs\RegrowthAddon\Export\BuildDataFile;
use App\Jobs\RegrowthAddon\Export\BuildItems;
use App\Jobs\RegrowthAddon\Export\BuildPlayerAttendance;
use App\Jobs\RegrowthAddon\Export\BuildPriorities;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PrepareRegrowthAddonData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prep-addon-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepares Regrowth Addon data by dispatching a batch of build jobs';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $latestReport = Report::latest()->first();
        $since = $latestReport?->end_time?->addSecond() ?? null;

        $guildTags = GuildTag::where('count_attendance', true)->get();

        $jobs = $guildTags->map(fn ($guildTag) => new FetchWarcraftLogsReportsByGuildTag($guildTag, $since));

        Bus::chain([
            Bus::batch($guildTags->map(fn ($guildTag) => new FetchWarcraftLogsReportsByGuildTag($guildTag, $since)))->before(function (Batch $batch) {
                Log::info('Starting batch to fetch Warcraft Logs reports for Regrowth Addon export.', [
                    'total_jobs' => $batch->totalJobs,
                ]);
            })->progress(function (Batch $batch) {
                Log::info('Batch progress for fetching data for Regrowth Addon export: '.$batch->progress().'% ('.$batch->processedJobs().'/'.$batch->totalJobs.')');
            })->catch(function (Batch $batch, Throwable $e) {
                Log::error('Batch to fetch data for Regrowth Addon export failed with error: '.$e->getMessage());
            })->then(function (Batch $batch) {
                Log::info('Batch to fetch data for Regrowth Addon export completed successfully.', [
                    'processed_jobs' => $batch->processedJobs(),
                ]);
            }),
            new FetchGuildRoster,
            new FetchWarcraftLogsAttendanceData($guildTags, $since),
            Bus::batch([
                new BuildPriorities,
                new BuildItems,
                new BuildPlayerAttendance,
                new BuildCouncillors,
            ]),
            new BuildDataFile,
        ])->catch(function (Throwable $e) {
            Log::error('Addon export batch failed: '.$e->getMessage());
            Cache::tags(['regrowth-addon:build'])->flush();
        })->dispatch();
    }
}
