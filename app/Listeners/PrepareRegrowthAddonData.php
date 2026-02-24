<?php

namespace App\Listeners;

use App\Events\AddonSettingsProcessed;
use App\Events\LootBiasPrioritiesProcessed;
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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PrepareRegrowthAddonData implements ShouldQueue
{
    /**
     * Cache key used to track when the addon export was last dispatched.
     */
    protected string $cacheKey = 'regrowth-addon.export.last-dispatched';

    /**
     * Throttle: do not dispatch more frequently than every 10 minutes.
     */
    protected int $throttleSeconds = 600;

    /**
     * Handle the event.
     */
    public function handle(AddonSettingsProcessed|LootBiasPrioritiesProcessed $event): void
    {
        if (! Cache::add($this->cacheKey, true, $this->throttleSeconds)) {
            return;
        }

        $latestReport = Report::latest()->first();
        $since = $latestReport?->end_time?->addSecond() ?? null;

        $guildTags = GuildTag::where('count_attendance', true)->get();

        $jobs = $guildTags->map(fn ($guildTag) => new FetchWarcraftLogsReportsByGuildTag($guildTag, $since));

        $jobs->push(new FetchGuildRoster);

        $jobs->push(new FetchWarcraftLogsAttendanceData($guildTags, $since));

        Bus::chain([
            Bus::batch($jobs->toArray()),
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
