<?php

namespace App\Listeners;

use App\Contracts\Events\PreparesRegrowthAddonData;
use App\Events\GrmUploadProcessed;
use App\Jobs\FetchGuildRoster;
use App\Jobs\FetchWarcraftLogsAttendanceData;
use App\Jobs\FetchWarcraftLogsReportsByGuildTag;
use App\Jobs\RegrowthAddon\Export\BuildCouncillors;
use App\Jobs\RegrowthAddon\Export\BuildDataFile;
use App\Jobs\RegrowthAddon\Export\BuildItems;
use App\Jobs\RegrowthAddon\Export\BuildPlayerAttendance;
use App\Jobs\RegrowthAddon\Export\BuildPriorities;
use App\Jobs\SendGrmUploadNotification;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use App\Notifications\DiscordNotifiable;
use App\Notifications\GrmUploadFailed;
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
    public function handle(PreparesRegrowthAddonData $event): void
    {
        $isGrmUpload = $event instanceof GrmUploadProcessed;

        if (! Cache::add($this->cacheKey, true, $this->throttleSeconds)) {
            if ($isGrmUpload) {
                dispatch(new SendGrmUploadNotification(
                    $event->processedCount,
                    $event->skippedCount,
                    $event->warningCount,
                    $event->errorCount,
                    $event->errors,
                ));
            }

            return;
        }

        $latestReport = Report::latest()->first();
        $since = $latestReport?->end_time?->addSecond() ?? null;

        $guildTags = GuildTag::where('count_attendance', true)->get();

        $jobs = $guildTags->map(fn ($guildTag) => new FetchWarcraftLogsReportsByGuildTag($guildTag, $since));

        $jobs->push(new FetchGuildRoster);

        $chain = [
            Bus::batch($jobs->toArray()),
            new FetchWarcraftLogsAttendanceData($guildTags, $since),
            Bus::batch([
                new BuildPriorities,
                new BuildItems,
                new BuildPlayerAttendance,
                new BuildCouncillors,
            ]),
            new BuildDataFile,
        ];

        if ($isGrmUpload) {
            $chain[] = new SendGrmUploadNotification(
                $event->processedCount,
                $event->skippedCount,
                $event->warningCount,
                $event->errorCount,
                $event->errors,
            );
        }

        Bus::chain($chain)->catch(function (Throwable $e) use ($isGrmUpload, $event) {
            Log::error('Addon export batch failed: '.$e->getMessage());
            Cache::tags(['regrowth-addon:build'])->flush();

            if ($isGrmUpload) {
                DiscordNotifiable::officer()->notify(
                    new GrmUploadFailed(
                        $event->processedCount,
                        $event->errorCount,
                        $event->errors,
                        'Addon export failed: '.$e->getMessage()
                    )
                );
            }
        })->dispatch();
    }
}
