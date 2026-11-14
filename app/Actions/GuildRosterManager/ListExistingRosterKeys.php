<?php

namespace App\Actions\GuildRosterManager;

use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\GuildRosterManager\RosterEntry;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lists which of the given keys hold a roster entry of the kind in the game version.
 */
class ListExistingRosterKeys
{
    use AsAction;

    /**
     * @param  Collection<int, string>  $keys
     * @return Collection<int, string>
     */
    public function handle(int $gameVersionId, RosterEntryKind $kind, Collection $keys): Collection
    {
        return RosterEntry::where('game_version_id', $gameVersionId)
            ->where('kind', $kind)
            ->whereIn('key', $keys)
            ->pluck('key');
    }
}
