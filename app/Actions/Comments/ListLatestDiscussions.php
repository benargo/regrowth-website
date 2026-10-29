<?php

namespace App\Actions\Comments;

use App\Data\Navigation\LatestDiscussionData;
use App\Models\Comment;
use App\Models\GameVersion;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lists the newest listable root comments on a game version's named items,
 * newest first, with each item's boss, icon and reply count. Empty without a version.
 */
class ListLatestDiscussions
{
    use AsAction;

    /**
     * @return Collection<int, LatestDiscussionData>
     */
    public function handle(?GameVersion $gameVersion, int $limit = 3): Collection
    {
        if ($gameVersion === null) {
            return collect();
        }

        return Comment::listableRoots()
            ->whereHasMorph('commentable', [Item::class], fn (Builder $query) => $query->where('game_version_id', $gameVersion->id)->whereNotNull('name'))
            ->with(['commentable.boss', 'commentable.media'])
            ->withCount('replies')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn (Comment $comment): LatestDiscussionData => LatestDiscussionData::fromComment($comment))
            ->toBase();
    }
}
