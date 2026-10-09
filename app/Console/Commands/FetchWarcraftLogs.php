<?php

namespace App\Console\Commands;

use App\Http\Integrations\WarcraftLogs\Exceptions\WarcraftLogsRequestException;
use App\Http\Integrations\WarcraftLogs\RateLimitResetCache;
use App\Jobs\WarcraftLogs\FetchAttendanceData;
use App\Jobs\WarcraftLogs\FetchGuildTags;
use App\Jobs\WarcraftLogs\FetchReportsByGuildTag;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Throwable;

#[Signature('fetch:warcraft-logs {--latest}')]
#[Description('Refreshes guild tags, reports and attendance for every Warcraft Logs guild.')]
class FetchWarcraftLogs extends Command
{
    /**
     * Execute the console command. Each guild is fetched once, however many
     * game versions share it.
     */
    public function handle(RateLimitResetCache $rateLimitReset): int
    {
        $skipped = $this->reportUnfetchableVersions();
        $queued = 0;
        $failed = 0;

        $guilds = Guild::orderBy('id')->get();

        foreach ($guilds as $index => $guild) {
            $this->info("Fetching Warcraft Logs data for guild {$guild->id}…");

            try {
                dispatch_sync(new FetchGuildTags($guild));
            } catch (RateLimitReachedException $exception) {
                $resetsAt = $rateLimitReset->resetsAt() ?? now()->addSeconds($exception->getLimit()->getRemainingSeconds());

                $this->error("Warcraft Logs rate limit reached; points reset at {$resetsAt->setTimezone(now()->getTimezone())->toDateTimeString()}.");
                $failed++;
                $skipped += $guilds->count() - $index - 1;

                break;
            } catch (WarcraftLogsRequestException|FatalRequestException $exception) {
                report($exception);

                $this->error("Failed to fetch guild tags for guild {$guild->id}: {$exception->getMessage()}");
                $failed++;

                continue;
            }

            $this->queueReportsAndAttendance($guild, $this->resolveSince($guild));
            $queued++;
        }

        $this->info("Warcraft Logs fetch complete: {$queued} queued, {$skipped} skipped, {$failed} failed.");

        return self::SUCCESS;
    }

    /**
     * Print one line per game version without a Warcraft Logs guild, whose
     * reports are never fetched, and return how many there are.
     */
    private function reportUnfetchableVersions(): int
    {
        $unfetchable = GameVersion::whereNull('warcraft_logs_guild_id')->orderBy('id')->get();

        foreach ($unfetchable as $version) {
            $this->warn("Skipping {$version->title}: no Warcraft Logs guild.");
        }

        return $unfetchable->count();
    }

    /**
     * With --latest, fetch only reports newer than the guild's newest stored
     * report. A guild with no reports yet, such as one an officer has just
     * added, gets its whole history.
     */
    private function resolveSince(Guild $guild): ?Carbon
    {
        if (! $this->option('latest')) {
            return null;
        }

        return $guild->reports()->latest()->first()?->end_time?->addSecond();
    }

    /**
     * Queue a batch fetching the reports of each of the guild's tags, then the
     * guild's attendance once the batch completes.
     */
    private function queueReportsAndAttendance(Guild $guild, ?Carbon $since): void
    {
        $guildTags = $guild->guildTags()->get();

        if ($guildTags->isEmpty()) {
            dispatch(new FetchAttendanceData($guild));

            return;
        }

        Bus::batch($guildTags->map(fn (GuildTag $guildTag) => new FetchReportsByGuildTag($guildTag, $since))->all())
            ->name("warcraftlogs:guild:{$guild->id}")
            ->then(function (Batch $batch) use ($guild): void {
                Log::info("Warcraft Logs report batch for guild {$guild->id} completed; fetching attendance.");

                dispatch(new FetchAttendanceData($guild));
            })
            ->catch(function (Batch $batch, Throwable $exception) use ($guild): void {
                Log::error("Warcraft Logs batch for guild {$guild->id} failed: {$exception->getMessage()}");
            })
            ->dispatch();
    }
}
