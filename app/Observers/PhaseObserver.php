<?php

namespace App\Observers;

use App\Jobs\WarcraftLogs\RefreshGuildReports;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Database\Eloquent\Builder;

class PhaseObserver
{
    /**
     * Handle the Phase "created" event.
     */
    public function created(Phase $phase): void
    {
        $this->refreshReportsOfGameVersions([$phase->game_version_id]);
    }

    /**
     * Handle the Phase "updated" event.
     */
    public function updated(Phase $phase): void
    {
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

        Event::whereHas('raids', fn (Builder $query) => $query->whereBelongsTo($phase))
            ->lazyById()
            ->each->refreshGameVersion();
    }

    /**
     * Handle the Phase "deleted" event.
     */
    public function deleted(Phase $phase): void
    {
        $this->refreshReportsOfGameVersions([$phase->game_version_id]);
    }

    /**
     * Re-derive the reports of the guilds behind the given game versions.
     *
     * @param  array<int, int|null>  $gameVersionIds
     */
    private function refreshReportsOfGameVersions(array $gameVersionIds): void
    {
        $guildIds = GameVersion::whereKey(array_unique(array_filter($gameVersionIds)))
            ->pluck('warcraft_logs_guild_id')
            ->filter()
            ->unique();

        foreach (Guild::whereKey($guildIds)->get() as $guild) {
            RefreshGuildReports::schedule($guild);
        }
    }
}
