<?php

namespace App\Http\Controllers\Api;

use App\Events\Broadcasts\CommentReactionChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreReactionRequest;
use App\Http\Resources\CommentReactionResource;
use App\Models\CommentReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
class CommentReactionController extends Controller
{
    /**
     * Record the authenticated user's reaction to a comment.
     *
     * Authorization lives in StoreCommentReactionRequest::authorize() rather
     * than an #[Authorize] attribute: `comment_id` arrives in the body, and
     * the attribute resolves its model from a route parameter.
     */
    public function store(StoreReactionRequest $request): JsonResponse
    {
        $reaction = $request->comment()->reactions()->create([
            'user_id' => $request->user()->id,
        ]);

        $reaction->load('user');

        broadcast(new CommentReactionChanged($reaction, 'created'))->toOthers();

        return CommentReactionResource::make($reaction)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Remove a reaction. Ownership is the only criterion.
     */
    #[Authorize('delete', 'reaction')]
    public function destroy(CommentReaction $reaction): Response
    {
        $reaction->delete();

        broadcast(new CommentReactionChanged($reaction, 'deleted'))->toOthers();

        return response()->noContent();
    }
}
