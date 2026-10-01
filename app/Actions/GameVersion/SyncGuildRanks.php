<?php

namespace App\Actions\GameVersion;

use App\Jobs\RefreshGuildRosterAfterEditing;
use App\Models\GameVersion;
use App\Models\GuildRank;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Replaces a game version's guild ranks with the given ordered list.
 */
class SyncGuildRanks
{
    use AsAction;

    /**
     * @param  list<array{id?: int|null, name: string, count_attendance: bool}>  $ranks  In rank order.
     */
    public function handle(GameVersion $gameVersion, array $ranks): void
    {
        DB::transaction(fn () => $this->sync($gameVersion, $ranks));
    }

    /**
     * @param  list<array{id?: int|null, name: string, count_attendance: bool}>  $ranks
     */
    private function sync(GameVersion $gameVersion, array $ranks): void
    {
        $keptIds = collect($ranks)->pluck('id')->filter()->all();

        $removed = $gameVersion->guildRanks()->whereKeyNot($keptIds)->get();
        $removed->each->delete();

        $reordered = $removed->isNotEmpty();
        $changed = $removed->isNotEmpty();

        foreach (array_values($ranks) as $index => $attributes) {
            $rank = $this->findOrMake($gameVersion, $attributes['id'] ?? null);

            $rank->fill([
                'name' => $attributes['name'],
                'count_attendance' => $attributes['count_attendance'],
                'sort_order' => $index,
            ])->save();

            $reordered = $reordered || $rank->wasRecentlyCreated || $rank->wasChanged('sort_order');
            $changed = $changed || $rank->wasRecentlyCreated || $rank->wasChanged();
        }

        if ($reordered) {
            RefreshGuildRosterAfterEditing::schedule($gameVersion);
        }

        if ($changed) {
            DB::afterCommit(fn () => Cache::tags(['attendance'])->flush());
        }
    }

    private function findOrMake(GameVersion $gameVersion, ?int $id): GuildRank
    {
        return $id === null
            ? $gameVersion->guildRanks()->make()
            : $gameVersion->guildRanks()->findOrFail($id);
    }
}
