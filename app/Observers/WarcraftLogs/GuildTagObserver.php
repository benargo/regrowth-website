<?php

namespace App\Observers\WarcraftLogs;

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
     * Handle the GuildTag "updated" event. Re-saving its reports lets each one
     * re-derive its game version when the tag moves phase.
     */
    public function updated(GuildTag $guildTag): void
    {
        Cache::tags(['db', 'lootcouncil'])->flush();

        if ($guildTag->wasChanged('phase_id')) {
            $guildTag->reports()->lazyById()->each->save();
        }
    }

    /**
     * Handle the GuildTag "deleted" event.
     */
    public function deleted(GuildTag $guildTag): void
    {
        Cache::tags(['db', 'lootcouncil'])->flush();
    }
}
