<?php

namespace App\Http\Controllers\Api;

use App\Events\Broadcasts\CommentChanged;
use App\Events\Broadcasts\CommentPosted;
use App\Events\Broadcasts\CommentRemoved;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Http\Requests\Comments\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Jobs\SyncCommentReplyCount;
use App\Models\Comment;
use App\Models\CommentRevision;
use App\Notifications\NewLootCouncilComment;
use App\Services\Discord\Discord;
use App\Services\Discord\Notifications\NotifiableChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

#[Middleware('auth:sanctum')]
class CommentController extends Controller
{
    public function __construct(
        protected Discord $discord,
    ) {}

    /**
     * Store a new comment against an allow-listed commentable.
     */
    #[Authorize('create', Comment::class)]
    public function store(StoreCommentRequest $request): JsonResponse
    {
        $commentable = $request->commentable();
        $root = $this->resolveThreadRoot($request->validated('parent_id'), $commentable);

        $comment = $commentable->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        // Set outside the create() payload: `parent_id` is deliberately not
        // fillable, so no mass-assign path can bypass the depth cap.
        if ($root !== null) {
            $comment->parent_id = $root->id;
            $comment->save();
        }

        $comment->load(['user', 'commentable', 'reactions.user']);

        if ($root === null) {
            NotifiableChannel::fromConfig('lootcouncil', $this->discord)->notify(
                new NewLootCouncilComment($comment)
            );
        } else {
            SyncCommentReplyCount::dispatch($root);
        }

        broadcast(new CommentPosted($comment))->toOthers();

        return CommentResource::make($comment)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Resolve the root of the thread a reply belongs to, enforcing that the
     * referenced parent is a real, live comment on the same commentable.
     *
     * `StoreCommentRequest` only guarantees `parent_id` exists in `comments`
     * at all — including soft-deleted rows, since `exists:` bypasses model
     * scopes. Trashed-ness and cross-commentable checks are business rules,
     * not shape validation, so they live here rather than in the Request;
     * an invalid parent is a 404 (the referenced resource is unavailable),
     * not a 422.
     *
     * Replying to a reply attaches the new comment to that reply's own root,
     * which makes depth >= 2 unreachable by construction rather than merely
     * rejected by validation.
     */
    private function resolveThreadRoot(?int $parentId, Model $commentable): ?Comment
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Comment::withTrashed()->find($parentId);

        if (
            $parent === null
            || $parent->trashed()
            || $parent->commentable_type !== $commentable::class
            || $parent->commentable_id !== $commentable->id
        ) {
            abort(404);
        }

        return $parent->isReply() ? $parent->parent : $parent;
    }

    /**
     * Update a comment's body and/or resolved status in place.
     *
     * In place, not copy-on-write: the primary key must not change, or every
     * pivot_comments_reactions row keyed on it is orphaned. A body change
     * records the *prior* body as a CommentRevision first.
     */
    #[Authorize('update', 'comment')]
    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        DB::transaction(function () use ($request, $comment): void {
            $newBody = $request->validated('body');

            if ($newBody !== null && $newBody !== $comment->body) {
                CommentRevision::create([
                    'comment_id' => $comment->id,
                    'body' => $comment->body,
                    'edited_by' => $request->user()->id,
                ]);
            }

            $comment->update($request->safe()->only(['body']) + [
                'is_resolved' => $request->validated('isResolved', $comment->is_resolved),
            ]);
        });

        $comment->load(['user', 'commentable', 'reactions.user']);

        broadcast(new CommentChanged($comment))->toOthers();

        return CommentResource::make($comment)->response();
    }

    /**
     * Soft-delete a comment, recording who deleted it.
     *
     * A root's replies are deliberately left in place — the root renders as a
     * tombstone above a surviving discussion.
     */
    #[Authorize('delete', 'comment')]
    public function destroy(Request $request, Comment $comment): Response
    {
        $root = $comment->isReply() ? $comment->parent : null;

        $comment->update(['deleted_by' => $request->user()->id]);
        $comment->delete();

        broadcast(new CommentRemoved($comment))->toOthers();

        if ($root !== null) {
            SyncCommentReplyCount::dispatch($root);
        }

        return response()->noContent();
    }
}
