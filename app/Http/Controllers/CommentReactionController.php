<?php

namespace App\Http\Controllers;

use App\Events\Broadcasts\CommentReactionChanged;
use App\Http\Requests\Comments\StoreReactionRequest;
use App\Http\Resources\CommentReactionResource;
use App\Models\CommentReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth')]
class CommentReactionController extends Controller
{
    /**
     * Record the authenticated user's reaction to the comment named by the
     * request body's `comment_id`, broadcast it to other viewers and return
     * it as a resource. StoreReactionRequest performs the authorization.
     */
    public function store(StoreReactionRequest $request): JsonResponse
    {
        $reaction = $request->comment()->reactions()->create([
            'user_id' => $request->user()->id,
        ]);

        $reaction->load('user.media');

        broadcast(new CommentReactionChanged($reaction, 'created'))->toOthers();

        return CommentReactionResource::make($reaction)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Remove a reaction owned by the authenticated user and broadcast the removal.
     */
    #[Authorize('delete', 'reaction')]
    public function destroy(CommentReaction $reaction): Response
    {
        $reaction->delete();

        broadcast(new CommentReactionChanged($reaction, 'deleted'))->toOthers();

        return response()->noContent();
    }
}
