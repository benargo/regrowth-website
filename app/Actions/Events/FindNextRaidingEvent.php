<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\GameVersion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Finds a game version's soonest live event that hasn't ended yet, including
 * one in progress. Null without a version.
 */
class FindNextRaidingEvent
{
    use AsAction;

    public function handle(?GameVersion $gameVersion): ?Event
    {
        if ($gameVersion === null) {
            return null;
        }

        return Event::live()
            ->where('game_version_id', $gameVersion->id)
            ->where('end_time', '>=', now())
            ->orderBy('start_time')
            ->first();
    }
}
