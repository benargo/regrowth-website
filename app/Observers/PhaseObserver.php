<?php

namespace App\Observers;

use App\Actions\Events\RefreshEventGameVersion;
use App\Events\RaidingDataChanged;
use App\Jobs\BuildAddonExportFile;
use App\Jobs\WarcraftLogs\RefreshGuildReports;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Builder;

class PhaseObserver
{
    /**
     * Handle the Phase "created" event.
     */
    public function created(Phase $phase): void
    {
        BuildAddonExportFile::schedule();
        RaidingDataChanged::dispatch($phase);

        $this->refreshReportsOfGameVersions([$phase->game_version_id]);
    }

    /**
     * Handle the Phase "updating" event: a phase that becomes unscheduled, or
     * moves to another game version while unscheduled, goes to the end of its
     * game version's unscheduled phases.
     */
    public function updating(Phase $phase): void
    {
        $isUnscheduled = $phase->start_date === null;

        if ($isUnscheduled && $phase->isDirty(['start_date', 'game_version_id'])) {
            $phase->setHighestOrderNumber();
        }
    }

    /**
     * Handle the Phase "updated" event.
     */
    public function updated(Phase $phase): void
    {
        BuildAddonExportFile::schedule();
        RaidingDataChanged::dispatch($phase);

        if (! $phase->wasChanged(['start_date', 'game_version_id'])) {
            return;
        }

        $this->refreshReportsOfGameVersions([
            $phase->game_version_id,
            $phase->getOriginal('game_version_id'),
        ]);

        if (! $phase->wasChanged('game_version_id')) {
            return;
        }

        $events = Event::whereHas('raids', fn (Builder $query) => $query->whereBelongsTo($phase))->lazyById();

        foreach ($events as $event) {
            RefreshEventGameVersion::run($event);
        }
    }

    /**
     * Handle the Phase "deleted" event.
     */
    public function deleted(Phase $phase): void
    {
        BuildAddonExportFile::schedule();
        RaidingDataChanged::dispatch($phase);

        $this->refreshReportsOfGameVersions([$phase->game_version_id]);
    }

    /**
     * Re-derive the reports of the guilds behind the given game versions.
     *
     * @param  array<int, int|null>  $gameVersionIds
     */
    private function refreshReportsOfGameVersions(array $gameVersionIds): void
    {
        RefreshGuildReports::scheduleForGuildIds(
            GameVersion::whereKey(array_unique(array_filter($gameVersionIds)))->pluck('warcraft_logs_guild_id'),
        );
    }
}
