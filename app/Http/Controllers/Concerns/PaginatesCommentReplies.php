<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;

trait PaginatesCommentReplies
{
    /**
     * The number of replies eager-loaded per root, and the page size of each
     * subsequent "load more" request.
     */
    public const REPLIES_PER_PAGE = 5;

    /**
     * The maximum number of roots a single `replies` request may ask for.
     */
    public const MAX_REPLY_ROOTS = 50;

    /**
     * The query replies are drawn from.
     *
     * Scoping this narrowly is the only authorization a reply request gets: an
     * item page returns `$item->comments()`, so a root belonging to another
     * commentable simply matches nothing.
     */
    abstract protected function repliesBaseQuery(): Builder;

    /**
     * The relations to eager-load onto every reply.
     *
     * @return array<int, string>
     */
    abstract protected function replyEagerLoads(): array;

    /**
     * Attach each root's first page of replies.
     *
     * A constrained eager load cannot LIMIT per parent — `limit()` inside the
     * closure caps the whole result set, not each root's slice. A page holds at
     * most 20 roots, so a loop of small indexed queries is simpler than a
     * window-function query and cheap enough at this size.
     *
     * @param  EloquentCollection<int, Comment>  $roots
     */
    protected function attachFirstRepliesPage(EloquentCollection $roots): void
    {
        $roots->each(function (Comment $root): void {
            $root->setRelation('replies', $root->replies()
                ->with($this->replyEagerLoads())
                ->limit(self::REPLIES_PER_PAGE)
                ->get());
        });
    }

    /**
     * Build the next page of replies for the requested roots, keyed by root id.
     *
     * `offsets` maps root id to how many replies the client already holds, so
     * each root resumes from its own position and several remembered threads
     * restore in a single request.
     *
     * Offsets are client-supplied and may be stale: unknown ids, non-roots, and
     * fully-loaded roots yield no entry rather than an error. A trashed root is
     * still paginated — its tombstone renders above a live discussion that must
     * remain reachable.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function buildRepliesProp(Request $request): array
    {
        $offsets = collect($request->input('offsets', []))
            ->mapWithKeys(fn (mixed $offset, mixed $rootId): array => [(int) $rootId => max(0, (int) $offset)])
            ->take(self::MAX_REPLY_ROOTS);

        if ($offsets->isEmpty()) {
            return [];
        }

        return $offsets
            ->map(fn (int $offset, int $rootId) => $this->repliesBaseQuery()
                ->where('parent_id', $rootId)
                ->with($this->replyEagerLoads())
                ->oldest()
                ->skip($offset)
                ->take(self::REPLIES_PER_PAGE)
                ->get())
            ->filter(fn (EloquentCollection $replies): bool => $replies->isNotEmpty())
            ->map(fn (EloquentCollection $replies) => CommentResource::collection($replies)->resolve($request))
            ->all();
    }
}
