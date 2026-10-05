<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\Phase;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;

class PhaseObserver
{
    /**
     * Handle the Phase "updated" event. When the phase moves to another game
     * version, re-resolve the reports on its guild tags and the events with a
     * raid in it.
     */
    public function updated(Phase $phase): void
    {
        if (! $phase->wasChanged('game_version_id')) {
            return;
        }

        Report::whereHas('guildTag', fn (Builder $query) => $query->whereBelongsTo($phase))
            ->lazyById()
            ->each->save();

        Event::whereHas('raids', fn (Builder $query) => $query->whereBelongsTo($phase))
            ->lazyById()
            ->each->refreshGameVersion();
    }
}
