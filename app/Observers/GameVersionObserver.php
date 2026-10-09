<?php

namespace App\Observers;

use App\Jobs\WarcraftLogs\RefreshGuildReports;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\Guild;

class GameVersionObserver
{
    /**
     * Handle the GameVersion "created" event.
     */
    public function created(GameVersion $gameVersion): void
    {
        $this->refreshReportsOfGuilds([$gameVersion->warcraft_logs_guild_id]);
    }

    /**
     * Handle the GameVersion "updated" event.
     */
    public function updated(GameVersion $gameVersion): void
    {
        if (! $gameVersion->wasChanged(['release_date', 'warcraft_logs_guild_id'])) {
            return;
        }

        $this->refreshReportsOfGuilds([
            $gameVersion->warcraft_logs_guild_id,
            $gameVersion->getOriginal('warcraft_logs_guild_id'),
        ]);
    }

    /**
     * Handle the GameVersion "deleted" event.
     */
    public function deleted(GameVersion $gameVersion): void
    {
        $this->refreshReportsOfGuilds([$gameVersion->warcraft_logs_guild_id]);
    }

    /**
     * @param  array<int, int|null>  $guildIds
     */
    private function refreshReportsOfGuilds(array $guildIds): void
    {
        foreach (Guild::whereKey(array_unique(array_filter($guildIds)))->get() as $guild) {
            RefreshGuildReports::schedule($guild);
        }
    }
}
