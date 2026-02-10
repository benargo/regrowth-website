<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Http\Resources\LootCouncil\CommentResource;
use App\Http\Resources\LootCouncil\ItemResource;
use App\Http\Resources\LootCouncil\PriorityResource;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Priority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class ItemController extends Controller
{
    /**
     * Display a specific loot item.
     */
    public function view(Item $item, Request $request)
    {
        $item->load([
            'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
            'raid',
            'boss',
        ]);

        $page = $request->integer('page', 1);

        $comments = Cache::tags(['lootcouncil'])
            ->remember(
                "item_{$item->id}_comments_page_{$page}",
                now()->addDay(),
                fn () => $item->comments()
                    ->with('user')
                    ->latest()
                    ->paginate(10)
            );

        return Inertia::render('LootBiasTool/ItemShow', [
            'item' => new ItemResource($item),
            'can' => [
                'create_comment' => $request->user()->can('create', Comment::class),
                'edit_item' => $request->user()->can('update', $item),
            ],
            'comments' => CommentResource::collection($comments),
        ]);
    }

    /**
     * Show the form for editing a specific loot item.
     */
    public function edit(Item $item, Request $request)
    {
        $item->load([
            'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
            'raid',
            'boss',
        ]);

        $page = $request->integer('page', 1);

        $comments = Cache::tags(['lootcouncil'])
            ->remember(
                "item_{$item->id}_comments_page_{$page}",
                now()->addDay(),
                fn () => $item->comments()
                    ->with('user')
                    ->latest()
                    ->paginate(10)
            );

        $allPriorities = Cache::tags(['lootcouncil'])->remember('priorities.all', now()->addYear(), fn () => Priority::all());

        return Inertia::render('LootBiasTool/ItemEdit', [
            'item' => new ItemResource($item),
            'allPriorities' => PriorityResource::collection($allPriorities),
            'can' => [
                'create_comment' => $request->user()->can('create', Comment::class),
                'edit_item' => $request->user()->can('edit-loot-items'),
            ],
            'comments' => CommentResource::collection($comments),
        ]);
    }
}
