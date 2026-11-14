<?php

namespace App\Actions\Characters;

use App\Models\Character;
use App\Models\GameVersion;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Marks a game version's in-guild characters missing from the Blizzard roster as left, and clears
 * the left marker of rostered characters who left before the roster was generated. Characters with
 * newer rank evidence stay in the guild. Does nothing for an empty roster and never deletes characters.
 */
class MarkGuildMembership
{
    use AsAction;

    /**
     * @param  Collection<int, int>  $memberIds  Blizzard character IDs on the roster at any level.
     * @param  CarbonInterface  $observedAt  The roster response's Last-Modified time.
     */
    public function handle(GameVersion $gameVersion, Collection $memberIds, CarbonInterface $observedAt): void
    {
        if ($memberIds->isEmpty()) {
            return;
        }

        $wallClock = $observedAt->copy()->setTimezone(config('app.timezone'));
        $ids = $memberIds->values()->all();

        $changed = DB::transaction(function () use ($gameVersion, $ids, $wallClock): int {
            $left = Character::where('game_version_id', $gameVersion->id)
                ->inGuild()
                ->whereNotIn('id', $ids)
                ->where(fn ($query) => $query->whereNull('rank_observed_at')->orWhere('rank_observed_at', '<', $wallClock))
                ->update(['left_guild_at' => $wallClock]);

            $returned = Character::where('game_version_id', $gameVersion->id)
                ->whereIn('id', $ids)
                ->where('left_guild_at', '<', $wallClock)
                ->update(['left_guild_at' => null]);

            return $left + $returned;
        });

        if ($changed > 0) {
            Cache::tags(['characters'])->flush();
        }
    }
}
