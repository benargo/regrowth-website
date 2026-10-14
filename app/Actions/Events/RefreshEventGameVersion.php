<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\Phase;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Stores the game version an event's raids share, or null when it has no
 * raids, its raids span several game versions, or a raid's phase has none.
 */
class RefreshEventGameVersion
{
    use AsAction;

    public function handle(Event $event): Event
    {
        $gameVersionIds = Phase::whereIn('id', $event->raids()->reorder()->select('raids.phase_id'))
            ->pluck('game_version_id')
            ->unique();

        $event->gameVersion()
            ->associate($gameVersionIds->containsOneItem() ? $gameVersionIds->first() : null)
            ->save();

        return $event;
    }
}
