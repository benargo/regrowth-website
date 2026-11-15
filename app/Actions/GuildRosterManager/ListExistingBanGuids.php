<?php

namespace App\Actions\GuildRosterManager;

use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\GuildRosterManager\Ban;
use App\Models\GuildRosterManager\RosterEntry;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lists which of the given GUIDs already have a ban in the game version: a ban roster entry or a
 * ban log row.
 */
class ListExistingBanGuids
{
    use AsAction;

    private const int CHUNK_SIZE = 500;

    /**
     * @param  Collection<int, string>  $guids
     * @return Collection<int, string>
     */
    public function handle(int $gameVersionId, Collection $guids): Collection
    {
        return $guids
            ->unique()
            ->chunk(self::CHUNK_SIZE)
            ->flatMap(fn (Collection $chunk): Collection => RosterEntry::where('game_version_id', $gameVersionId)
                ->where('kind', RosterEntryKind::Ban)
                ->whereIn('key', $chunk->values())
                ->pluck('key')
                ->merge(Ban::where('game_version_id', $gameVersionId)->whereIn('grm_guid', $chunk->values())->pluck('grm_guid')))
            ->unique()
            ->values();
    }
}
