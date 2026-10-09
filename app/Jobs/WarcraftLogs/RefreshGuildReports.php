<?php

namespace App\Jobs\WarcraftLogs;

use App\Models\WarcraftLogs\Guild;
use App\Observers\ReportObserver;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-saves every report of a Warcraft Logs guild, so each one re-derives its
 * game version and phase after the versions or phases behind it changed.
 */
class RefreshGuildReports implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /**
     * Drop the job if the guild is deleted before it runs.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Let the unique lock lapse if a pending refresh is lost from the queue,
     * so later edits can still schedule one.
     */
    public int $uniqueFor = 600;

    public function __construct(public Guild $guild) {}

    /**
     * Queue a refresh for after the current transaction commits. A refresh
     * already waiting covers the new edit too; one that has started does not,
     * so a second is queued behind it.
     */
    public static function schedule(Guild $guild): void
    {
        static::dispatch($guild)->afterCommit();
    }

    /**
     * One pending refresh per guild.
     */
    public function uniqueId(): string
    {
        return (string) $this->guild->getKey();
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['warcraftlogs', 'refresh-reports', "warcraft-logs-guild:{$this->guild->getKey()}"];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ReportObserver::deferFlushing(fn () => $this->guild->reports()->lazyById()->each->save());
    }
}
