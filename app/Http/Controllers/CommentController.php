<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommentController extends Controller
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
     * Display a listing of every root comment across all commentables.
     */
    public function index(Request $request): Response
    {
        $comments = Comment::withTrashed()
            ->topLevel()
            ->where(fn (Builder $query) => $query
                ->whereNull('deleted_at')
                ->orWhereHas('replies')
            )
            ->withCount('replies')
            ->with(['user', 'commentable', 'reactions.user'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $this->attachFirstRepliesPage($comments->getCollection());

        return Inertia::render('Loot/Comments', [
            'comments' => CommentResource::collection($comments),
            'replies' => Inertia::optional(fn () => $this->buildRepliesProp($request)),
        ]);
    }

    /**
     * Attach each root's first page of replies.
     *
     * A constrained eager load cannot LIMIT per parent, so each root is fetched
     * individually; a page holds at most 20 roots.
     *
     * @param  Collection<int, Comment>  $roots
     */
    private function attachFirstRepliesPage(Collection $roots): void
    {
        $roots->each(function (Comment $root): void {
            $root->setRelation('replies', $root->replies()
                ->with(['user', 'reactions.user'])
                ->limit(self::REPLIES_PER_PAGE)
                ->get());
        });
    }

    /**
     * Build the next page of replies for the requested roots, keyed by root id.
     *
     * Unlike the item page, this index has no single commentable to scope by,
     * so the requested ids are constrained to genuine top-level comments — the
     * roots this index would itself list. Unknown ids, replies, and
     * fully-loaded roots yield no entry rather than an error.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildRepliesProp(Request $request): array
    {
        $offsets = collect($request->input('offsets', []))
            ->mapWithKeys(fn (mixed $offset, mixed $rootId): array => [(int) $rootId => max(0, (int) $offset)])
            ->take(self::MAX_REPLY_ROOTS);

        if ($offsets->isEmpty()) {
            return [];
        }

        $rootIds = Comment::withTrashed()
            ->topLevel()
            ->where(fn (Builder $query) => $query
                ->whereNull('deleted_at')
                ->orWhereHas('replies')
            )
            ->whereIn('id', $offsets->keys())
            ->pluck('id');

        return $offsets
            ->only($rootIds)
            ->map(fn (int $offset, int $rootId) => Comment::where('parent_id', $rootId)
                ->with(['user', 'commentable', 'reactions.user'])
                ->oldest()
                ->skip($offset)
                ->take(self::REPLIES_PER_PAGE)
                ->get())
            ->filter(fn (Collection $replies): bool => $replies->isNotEmpty())
            ->map(fn (Collection $replies) => CommentResource::collection($replies)->resolve($request))
            ->all();
    }
}
