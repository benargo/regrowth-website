<?php

namespace App\Console\Commands;

use App\Http\Integrations\WarcraftLogs\Exceptions\WarcraftLogsRequestException;
use App\Http\Integrations\WarcraftLogs\RateLimitResetCache;
use App\Jobs\WarcraftLogs\FetchAttendanceData;
use App\Jobs\WarcraftLogs\FetchGuildTags;
use App\Jobs\WarcraftLogs\FetchReportsByGuildTag;
use App\Models\GameVersion;
use App\Models\Report;
use App\Models\WarcraftLogs\GuildTag;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Throwable;

#[Signature('fetch:warcraft-logs {--latest}')]
#[Description('Refreshes Warcraft Logs guild tags, reports and attendance for every game version with a Warcraft Logs guild and namespace.')]
class FetchWarcraftLogs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RateLimitResetCache $rateLimitReset): int
    {
        $skipped = $this->reportUnfetchableVersions();
        $queued = 0;
        $failed = 0;
        $since = $this->resolveSince();

        $versions = GameVersion::query()
            ->whereNotNull('warcraftlogs_guild')
            ->whereNotNull('warcraftlogs_namespace')
            ->orderBy('id')
            ->get()
            ->values();

        foreach ($versions as $index => $version) {
            $this->info("Fetching Warcraft Logs data for {$version->title}…");

            try {
                dispatch_sync(new FetchGuildTags($version));
            } catch (RateLimitReachedException $exception) {
                $resetsAt = $rateLimitReset->resetsAt() ?? now()->addSeconds($exception->getLimit()->getRemainingSeconds());

                $this->error("Warcraft Logs rate limit reached; points reset at {$resetsAt->setTimezone(now()->getTimezone())->toDateTimeString()}.");
                $failed++;
                $skipped += $versions->count() - $index - 1;

                break;
            } catch (WarcraftLogsRequestException|FatalRequestException $exception) {
                report($exception);

                $this->error("Failed to fetch guild tags for {$version->title}: {$exception->getMessage()}");
                $failed++;

                continue;
            }

            $this->queueReportsAndAttendance($version, $since);
            $queued++;
        }

        $this->info("Warcraft Logs fetch complete: {$queued} queued, {$skipped} skipped, {$failed} failed.");

        return self::SUCCESS;
    }

    /**
     * Print one line per game version that cannot be fetched, and return how many there are.
     */
    private function reportUnfetchableVersions(): int
    {
        $unfetchable = GameVersion::query()
            ->where(fn (Builder $query) => $query->whereNull('warcraftlogs_guild')->orWhereNull('warcraftlogs_namespace'))
            ->orderBy('id')
            ->get();

        foreach ($unfetchable as $version) {
            $this->warn("Skipping {$version->title}: no Warcraft Logs guild or namespace.");
        }

        return $unfetchable->count();
    }

    /**
     * With --latest, fetch only reports newer than the newest stored report across all
     * versions.
     */
    private function resolveSince(): ?Carbon
    {
        if (! $this->option('latest')) {
            return null;
        }

        return Report::latest()->first()?->end_time?->addSecond();
    }

    private function queueReportsAndAttendance(GameVersion $version, ?Carbon $since): void
    {
        $guildTags = $version->guildTags()->with('phase.gameVersion')->get();

        if ($guildTags->isEmpty()) {
            dispatch(new FetchAttendanceData($version));

            return;
        }

        Bus::batch($guildTags->map(fn (GuildTag $guildTag) => new FetchReportsByGuildTag($guildTag, $since))->all())
            ->name("warcraftlogs:{$version->id}")
            ->then(function (Batch $batch) use ($version): void {
                Log::info("Warcraft Logs report batch for game version {$version->id} completed; fetching attendance.");

                dispatch(new FetchAttendanceData($version));
            })
            ->catch(function (Batch $batch, Throwable $exception) use ($version): void {
                Log::error("Warcraft Logs batch for game version {$version->id} failed: {$exception->getMessage()}");
            })
            ->dispatch();
    }
}
