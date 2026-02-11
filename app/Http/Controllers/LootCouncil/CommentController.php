<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Http\Requests\Comments\UpdateCommentRequest;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Notifications\DiscordNotifiable;
use App\Notifications\NewLootCouncilComment;
use App\Services\LootCouncil\LootCouncilCacheService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function __construct(
        protected LootCouncilCacheService $cacheService
    ) {}

    /**
     * Store a new comment for a specific loot item.
     */
    public function store(StoreCommentRequest $request, Item $item): RedirectResponse
    {
        $comment = $item->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $comment->load(['user', 'item']);

        DiscordNotifiable::channel('lootcouncil')->notify(
            new NewLootCouncilComment($comment)
        );

        $this->cacheService->flush();

        return redirect()->back();
    }

    /**
     * Update an existing comment for a specific loot item.
     */
    public function update(UpdateCommentRequest $request, Comment $comment): RedirectResponse
    {
        // Create new comment with original timestamp
        $newComment = new Comment([
            'item_id' => $comment->item_id,
            'user_id' => $comment->user_id,
            'body' => $request->validated('body', $comment->body), // Preserve original body if not provided
            'is_resolved' => $request->validated('isResolved', $comment->is_resolved), // Preserve resolved status if not provided
        ]);
        $newComment->created_at = $comment->created_at;
        $newComment->save();

        // Soft delete the original comment, tracking who edited it
        $comment->update(['deleted_by' => $request->user()->id]);
        $comment->delete();

        $this->cacheService->flush();

        return redirect()->back();
    }

    /**
     * Delete a comment for a specific loot item.
     */
    public function destroy(Request $request, Item $item, Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->update(['deleted_by' => $request->user()->id]);
        $comment->delete();

        $this->cacheService->flush();

        return redirect()->back();
    }
}
