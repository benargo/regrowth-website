<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesCommentReplies;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommentController extends Controller
{
    use PaginatesCommentReplies;

    /**
     * Display a listing of every root comment across all commentables.
     */
    public function index(Request $request): Response
    {
        $comments = Comment::listableRoots()
            ->withCount('replies')
            ->with($this->replyEagerLoads())
            ->latest()
            ->paginate(20);

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
        return ['user', 'commentable', 'reactions.user'];
    }
}
