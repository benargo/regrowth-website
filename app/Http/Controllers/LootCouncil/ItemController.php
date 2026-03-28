<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Http\Resources\LootCouncil\CommentResource;
use App\Http\Resources\LootCouncil\ItemResource;
use App\Http\Resources\LootCouncil\PriorityResource;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Models\User;
use App\Services\Blizzard\ItemService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ItemController extends Controller
{
    /**
     * Display a specific loot item.
     */
    public function view(ItemService $itemService, Request $request, Item $item, ?string $name = null)
    {
        $slug = Str::slug(Arr::get($itemService->find($item->id), 'name') ?? "item-{$item->id}");

        if ($name !== $slug) {
            return redirect()->route('loot.items.show', ['item' => $item->id, 'name' => $slug], 303);
        }

        $item->load([
            'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
            'raid',
            'boss',
        ]);

        $comments = $this->getCachedComments($item, $request->integer('page', 1));

        return Inertia::render('LootBiasTool/ItemShow', [
            'item' => new ItemResource($item),
            'comments' => CommentResource::collection($comments),
        ]);
    }

    /**
     * Show the form for editing a specific loot item.
     */
    public function edit(ItemService $itemService, Request $request, Item $item, ?string $name = null)
    {
        $slug = Str::slug(Arr::get($itemService->find($item->id), 'name') ?? "item-{$item->id}");

        if ($name !== $slug) {
            return redirect()->route('loot.items.edit', ['item' => $item->id, 'name' => $slug], 303);
        }

        $item->load([
            'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
            'raid',
            'boss',
        ]);

        $comments = $this->getCachedComments($item, $request->integer('page', 1));

        $allPriorities = Priority::hydrate(
            Cache::tags(['lootcouncil'])->remember('priorities.all', now()->addYear(), fn () => Priority::all()->map->getAttributes()->toArray())
        );

        return Inertia::render('LootBiasTool/ItemEdit', [
            'item' => new ItemResource($item),
            'allPriorities' => PriorityResource::collection($allPriorities),
            'comments' => CommentResource::collection($comments),
        ]);
    }

    public function redirectToEdit(ItemService $itemService, Item $item)
    {
        $slug = Str::slug(Arr::get($itemService->find($item->id), 'name') ?? "item-{$item->id}");

        return redirect()->route('loot.items.edit', ['item' => $item->id, 'name' => $slug], 303);
    }

    /**
     * Get paginated comments for an item, using the cache where possible.
     */
    private function getCachedComments(Item $item, int $page): LengthAwarePaginator
    {
        $cachedData = Cache::tags(['lootcouncil'])
            ->remember(
                "item_{$item->id}_comments_page_{$page}",
                now()->addDay(),
                fn () => $item->comments()
                    ->with('user')
                    ->latest()
                    ->paginate(10)
                    ->through(fn ($comment) => array_merge(
                        $comment->getAttributes(),
                        ['_user' => $comment->user?->toArray()]
                    ))
                    ->toArray()
            );

        $commentModels = Comment::hydrate(
            collect($cachedData['data'])->map(fn ($d) => Arr::except($d, ['_user']))->toArray()
        )->each(function ($comment, $i) use ($cachedData) {
            $userData = $cachedData['data'][$i]['_user'] ?? null;
            if ($userData) {
                $comment->setRelation('user', (new User)->forceFill($userData));
            }
        });

        return new LengthAwarePaginator(
            $commentModels,
            $cachedData['total'],
            $cachedData['per_page'],
            $cachedData['current_page'],
            ['path' => $cachedData['path']]
        );
    }
}
