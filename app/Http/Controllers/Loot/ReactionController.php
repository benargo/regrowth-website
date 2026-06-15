<?php

namespace App\Http\Controllers\Loot;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\DestroyReactionRequest;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\CommentReaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth')]
class ReactionController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    #[Authorize('react', 'comment')]
    public function store(Request $request, Comment $comment): RedirectResponse
    {
        $comment->reactions()->create([
            'user_id' => $request->user()->id,
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    #[Authorize('react', 'comment')]
    public function destroy(DestroyReactionRequest $request, Comment $comment, CommentReaction $reaction): RedirectResponse
    {
        $reaction->delete();

        return redirect()->back();
    }
}
