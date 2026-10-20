<?php

namespace App\Actions\Items;

use App\Data\Items\LootStatsData;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\ItemPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Count the given game version's items, its distinct item priority rows, the
 * comments on its items, their commenters, and the reactions to those comments,
 * cached for ten minutes per version. Every count is zero without a game version.
 */
class BuildLootStats
{
    use AsAction;

    public function handle(?GameVersion $gameVersion): LootStatsData
    {
        if ($gameVersion === null) {
            return LootStatsData::blank();
        }

        return LootStatsData::from(Cache::tags(['lootcouncil'])->remember(
            "loot:stats:{$gameVersion->id}",
            now()->addMinutes(10),
            fn (): array => $this->build($gameVersion)->toArray(),
        ));
    }

    /**
     * Count the game version's loot records, uncached.
     */
    private function build(GameVersion $gameVersion): LootStatsData
    {
        $inVersion = fn (Builder $query) => $query->where('game_version_id', $gameVersion->id);
        $comments = Comment::whereHasMorph('commentable', [Item::class], $inVersion);

        return new LootStatsData(
            itemsCount: Item::where('game_version_id', $gameVersion->id)->count(),
            priorityRowsCount: ItemPriority::whereHas('item', $inVersion)->distinct()->count(['item_id', 'weight']),
            commentsCount: $comments->clone()->count(),
            commentersCount: $comments->clone()->distinct('user_id')->count('user_id'),
            reactionsCount: CommentReaction::whereIn('comment_id', $comments->clone()->select('id'))->count(),
        );
    }
}
