<?php

namespace App\Http\Controllers\Api\LootCouncil;

use App\Http\Controllers\Controller;
use App\Models\LootCouncil\Comment;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    /**
     * Resolve a loot council comment.
     *
     * Creates a new resolved comment and soft-deletes the original,
     * following the existing update pattern for consistency.
     */
    public function resolve(Comment $comment): JsonResponse
    {
        abort_unless(
            request()->bearerToken() === config('services.discord.token'),
            403,
        );

        $newComment = new Comment([
            'item_id' => $comment->item_id,
            'user_id' => $comment->user_id,
            'body' => $comment->body,
            'is_resolved' => true,
        ]);
        $newComment->created_at = $comment->created_at;
        $newComment->save();

        $comment->delete();

        return response()->json($newComment);
    }
}
