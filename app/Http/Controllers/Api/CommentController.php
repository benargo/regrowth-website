<?php

namespace App\Http\Controllers\Api;

use App\Events\Broadcasts\CommentChanged;
use App\Events\Broadcasts\CommentPosted;
use App\Events\Broadcasts\CommentRemoved;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Http\Requests\Comments\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\CommentRevision;
use App\Notifications\NewLootCouncilComment;
use App\Services\Discord\Discord;
use App\Services\Discord\Notifications\NotifiableChannel;
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

        $comment = $commentable->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $comment->load(['user', 'commentable', 'reactions.user']);

        NotifiableChannel::fromConfig('lootcouncil', $this->discord)->notify(
            new NewLootCouncilComment($comment)
        );

        broadcast(new CommentPosted($comment))->toOthers();

        return CommentResource::make($comment)
            ->response()
            ->setStatusCode(201);
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
     */
    #[Authorize('delete', 'comment')]
    public function destroy(Request $request, Comment $comment): Response
    {
        $comment->update(['deleted_by' => $request->user()->id]);
        $comment->delete();

        broadcast(new CommentRemoved($comment))->toOthers();

        return response()->noContent();
    }
}
