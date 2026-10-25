<?php

namespace App\Http\Controllers;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Http\Controllers\Concerns\PaginatesCommentReplies;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class CommentController extends Controller
{
    use PaginatesCommentReplies;

    private const int PER_PAGE = 20;

    /**
     * Display a listing of every root comment on the current game version's items.
     */
    public function index(Request $request): Response
    {
        $gameVersion = ResolveCurrentGameVersion::run();

        $comments = $gameVersion === null
            ? new LengthAwarePaginator(new EloquentCollection, 0, self::PER_PAGE)
            : Comment::listableRoots()
                ->whereHasMorph('commentable', [Item::class], fn (Builder $query) => $query->where('game_version_id', $gameVersion->id))
                ->withCount('replies')
                ->with($this->replyEagerLoads())
                ->latest()
                ->paginate(self::PER_PAGE);

        $this->attachFirstRepliesPage($comments->getCollection());

        return Inertia::render('Loot/Comments', [
            'comments' => CommentResource::collection($comments),
            'replies' => Inertia::optional(fn () => $this->buildRepliesProp($request)),
        ]);
    }

    /**
     * The query replies are drawn from.
     *
     * This index has no single commentable to scope by, so requested ids are
     * constrained to replies whose parent is a genuine top-level comment — the
     * roots this index would itself list. A trashed root still qualifies:
     * `topLevel` alone does not filter soft deletes, so a tombstoned thread
     * remains paginable.
     */
    protected function repliesBaseQuery(): Builder
    {
        return Comment::whereHas('parent', fn (Builder $query) => $query->withTrashed()->topLevel());
    }

    /**
     * @return array<int, string>
     */
    protected function replyEagerLoads(): array
    {
        return ['user.media', 'commentable', 'reactions.user.media'];
    }
}
