<?php

namespace App\Observers;

use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Support\Facades\Cache;

class GuildTagObserver
{
    /**
     * Handle the GuildTag "created" event.
     */
    public function created(GuildTag $guildTag): void
    {
        Cache::tags(['db', 'lootcouncil'])->flush();
    }

    /**
     * Handle the GuildTag "updated" event.
     */
    public function updated(GuildTag $guildTag): void
    {
        Cache::tags(['db', 'lootcouncil'])->flush();
    }

    /**
     * Handle the GuildTag "deleted" event.
     */
    public function deleted(GuildTag $guildTag): void
    {
        Cache::tags(['db', 'lootcouncil'])->flush();
    }
}
