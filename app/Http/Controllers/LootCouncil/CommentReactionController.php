<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\DestroyCommentReactionRequest;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\CommentReaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentReactionController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Comment $comment): RedirectResponse
    {
        Gate::authorize('react', $comment);

        $comment->reactions()->create([
            'user_id' => $request->user()->id,
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DestroyCommentReactionRequest $request, Comment $comment, CommentReaction $reaction): RedirectResponse
    {
        $reaction->delete();

        return redirect()->back();
    }
}
