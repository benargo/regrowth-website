<?php

namespace App\Http\Controllers\Api\Loot;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

#[Middleware('auth:sanctum')]
#[Authorize('markAsResolved', 'comment')]
class ResolveCommentController extends Controller
{
    public function __invoke(Comment $comment): JsonResponse
    {
        $newComment = DB::transaction(function () use ($comment) {
            $newComment = new Comment([
                'commentable_id' => $comment->commentable_id,
                'commentable_type' => $comment->commentable_type,
                'user_id' => $comment->user_id,
                'body' => $comment->body,
                'is_resolved' => true,
            ]);
            $newComment->created_at = $comment->created_at;
            $newComment->save();

            $comment->delete();

            return $newComment;
        });

        return response()->json($newComment);
    }
}
