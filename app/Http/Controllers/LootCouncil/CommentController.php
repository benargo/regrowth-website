<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Items\StoreItemCommentRequest;
use App\Http\Requests\Items\UpdateItemCommentRequest;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemComment;
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
    public function store(StoreItemCommentRequest $request, Item $item): RedirectResponse
    {
        $item->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $this->cacheService->flush();

        return redirect()->back();
    }

    /**
     * Update an existing comment for a specific loot item.
     */
    public function update(UpdateItemCommentRequest $request, Item $item, ItemComment $comment): RedirectResponse
    {
        $originalCreatedAt = $comment->created_at;

        // Soft delete the original comment, tracking who edited it
        $comment->update(['deleted_by' => $request->user()->id]);
        $comment->delete();

        // Create new comment with original timestamp
        $newComment = new ItemComment([
            'item_id' => $item->id,
            'user_id' => $comment->user_id,
            'body' => $request->validated('body'),
        ]);
        $newComment->created_at = $originalCreatedAt;
        $newComment->save();

        $this->cacheService->flush();

        return redirect()->back();
    }

    /**
     * Delete a comment for a specific loot item.
     */
    public function destroy(Request $request, Item $item, ItemComment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->update(['deleted_by' => $request->user()->id]);
        $comment->delete();

        $this->cacheService->flush();

        return redirect()->back();
    }
}
