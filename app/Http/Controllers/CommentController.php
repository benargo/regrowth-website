<?php

namespace App\Http\Controllers;

use App\Actions\Comments\ResolveCommentThreadRoot;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Events\Broadcasts\CommentChanged;
use App\Events\Broadcasts\CommentPosted;
use App\Events\Broadcasts\CommentRemoved;
use App\Http\Controllers\Concerns\PaginatesCommentReplies;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Http\Requests\Comments\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Jobs\Discord\NotifyDiscordChannel;
use App\Models\Comment;
use App\Models\Item;
use App\Notifications\CommentPostedToItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
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
     * Store a new comment against an allow-listed commentable, then redirect back.
     */
    #[Middleware('auth')]
    #[Authorize('create', Comment::class)]
    public function store(StoreCommentRequest $request): RedirectResponse
    {
        $commentable = $request->commentable();
        $root = ResolveCommentThreadRoot::run($request->validated('parent_id'), $commentable);

        $comment = $commentable->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        if ($root !== null) {
            $comment->parent()->associate($root)->save();
        }

        $comment->load(['user.media', 'commentable', 'reactions.user.media']);

        NotifyDiscordChannel::dispatch(
            config('services.discord.channels.lootcouncil'),
            $root !== null ? CommentPostedToItem::refresh($root) : new CommentPostedToItem($comment),
        );

        broadcast(new CommentPosted($comment))->toOthers();

        return back();
    }

    /**
     * Update a comment's body and/or resolved status in place, recording the
     * prior body as a CommentRevision when the body changes, then redirect back.
     */
    #[Middleware('auth')]
    #[Authorize('update', 'comment')]
    public function update(UpdateCommentRequest $request, Comment $comment): RedirectResponse
    {
        DB::transaction(function () use ($request, $comment): void {
            $newBody = $request->validated('body');

            if ($newBody !== null && $newBody !== $comment->body) {
                $comment->revisions()->create([
                    'body' => $comment->body,
                    'edited_by' => $request->user()->id,
                ]);
            }

            $comment->update($request->safe()->only(['body']) + [
                'is_resolved' => $request->validated('isResolved', $comment->is_resolved),
            ]);
        });

        $comment->load(['user.media', 'commentable', 'reactions.user.media']);

        broadcast(new CommentChanged($comment))->toOthers();

        return back();
    }

    /**
     * Soft-delete a comment, recording who deleted it, refresh the thread's
     * Discord message when the comment is a reply, then redirect back. Replies
     * to a deleted root are left in place.
     */
    #[Middleware('auth')]
    #[Authorize('delete', 'comment')]
    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        $root = $comment->parent;

        DB::transaction(function () use ($request, $comment): void {
            $comment->update(['deleted_by' => $request->user()->id]);
            $comment->delete();
        });

        broadcast(new CommentRemoved($comment))->toOthers();

        if ($root !== null) {
            NotifyDiscordChannel::dispatch(config('services.discord.channels.lootcouncil'), CommentPostedToItem::refresh($root));
        }

        return back();
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
