<?php

namespace App\Http\Controllers\Api;

use App\Actions\Comments\ResolveCommentThreadRoot;
use App\Events\Broadcasts\CommentChanged;
use App\Events\Broadcasts\CommentPosted;
use App\Events\Broadcasts\CommentRemoved;
use App\Http\Controllers\Controller;
use App\Http\Integrations\Discord\Data\Channels\ChannelStub;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Http\Requests\Comments\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Notifications\CommentPostedToItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

#[Middleware('auth:sanctum')]
class CommentController extends Controller
{
    /**
     * Store a new comment against an allow-listed commentable.
     */
    #[Authorize('create', Comment::class)]
    public function store(StoreCommentRequest $request): JsonResponse
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

        $this->lootCouncilChannel()->notify(
            $root !== null ? CommentPostedToItem::refresh($root) : new CommentPostedToItem($comment),
        );

        broadcast(new CommentPosted($comment))->toOthers();

        return CommentResource::make($comment)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a comment's body and/or resolved status in place, recording the
     * prior body as a CommentRevision when the body changes.
     */
    #[Authorize('update', 'comment')]
    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
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

        return CommentResource::make($comment)->response();
    }

    /**
     * Soft-delete a comment, recording who deleted it, and refresh the
     * thread's Discord message when the comment is a reply. Replies to a
     * deleted root are left in place.
     */
    #[Authorize('delete', 'comment')]
    public function destroy(Request $request, Comment $comment): Response
    {
        $root = $comment->parent;

        DB::transaction(function () use ($request, $comment): void {
            $comment->update(['deleted_by' => $request->user()->id]);
            $comment->delete();
        });

        broadcast(new CommentRemoved($comment))->toOthers();

        if ($root !== null) {
            $this->lootCouncilChannel()->notify(CommentPostedToItem::refresh($root));
        }

        return response()->noContent();
    }

    /**
     * Get the loot council Discord channel.
     */
    private function lootCouncilChannel(): ChannelStub
    {
        return new ChannelStub(config('services.discord.channels.lootcouncil'));
    }
}
