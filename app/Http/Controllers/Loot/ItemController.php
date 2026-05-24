<?php

namespace App\Http\Controllers\Loot;

use App\Http\Controllers\Controller;
use App\Http\Requests\Items\UpdateItemNotesRequest;
use App\Http\Requests\Items\UpdateItemPrioritiesRequest;
use App\Http\Resources\LootCouncil\CommentResource;
use App\Http\Resources\LootCouncil\ItemResource;
use App\Http\Resources\LootCouncil\ItemSearchResource;
use App\Http\Resources\LootCouncil\PriorityResource;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Services\Blizzard\BlizzardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ItemController extends Controller
{
    /**
     * Display a specific loot item.
     */
    public function view(BlizzardService $blizzard, Request $request, Item $item, ?string $name = null): InertiaResponse|RedirectResponse
    {
        $slug = $this->resolveItemSlug($blizzard, $item);

        if ($name !== $slug) {
            return redirect()->route('loot.items.show', ['item' => $item->id, 'name' => $slug], 303);
        }

        $this->loadItemWithRelations($item);

        return Inertia::render('LootBiasTool/ItemShow', [
            'item' => new ItemResource($item),
            'comments' => CommentResource::collection($this->paginateComments($item)),
        ]);
    }

    /**
     * Show the form for editing a specific loot item.
     */
    public function edit(BlizzardService $blizzard, Request $request, Item $item, ?string $name = null): InertiaResponse|RedirectResponse
    {
        $slug = $this->resolveItemSlug($blizzard, $item);

        if ($name !== $slug) {
            return redirect()->route('loot.items.edit', ['item' => $item->id, 'name' => $slug], 303);
        }

        $this->loadItemWithRelations($item);

        $allPriorities = Priority::hydrate(
            Cache::tags(['db', 'lootcouncil'])->remember('priorities:all', now()->addYear(), fn () => Priority::all()->map->getAttributes()->toArray())
        );

        return Inertia::render('LootBiasTool/ItemEdit', [
            'item' => new ItemResource($item),
            'allPriorities' => PriorityResource::collection($allPriorities),
            'comments' => CommentResource::collection($this->paginateComments($item)),
        ]);
    }

    /**
     * Redirect to the edit page for a specific loot item.
     */
    public function redirectToEdit(BlizzardService $blizzard, Item $item): RedirectResponse
    {
        return redirect()->route('loot.items.edit', ['item' => $item->id, 'name' => $this->resolveItemSlug($blizzard, $item)], 303);
    }

    /**
     * Update the officers' notes for a specific loot item.
     */
    public function updateNotes(UpdateItemNotesRequest $request, Item $item): RedirectResponse
    {
        $item->notes = $request->validated('notes');
        $item->save();

        return redirect()->back();
    }

    /**
     * Update the priorities for a specific loot item.
     */
    public function updatePriorities(UpdateItemPrioritiesRequest $request, Item $item): RedirectResponse
    {
        $priorities = collect($request->validated('priorities'))
            ->mapWithKeys(fn ($p) => [$p['priority_id'] => ['weight' => $p['weight']]])
            ->all();

        $item->priorities()->sync($priorities);

        return redirect()->back();
    }

    /**
     * Search for loot items by name.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->string('query')->trim();

        if ($query->isEmpty()) {
            return response()->json([]);
        }

        $items = Item::query()
            ->where('name', 'like', '%'.$query.'%')
            ->withCount(['priorities', 'comments'])
            ->limit(10)
            ->get();

        return response()->json(
            ItemSearchResource::collection($items)->resolve($request)
        );
    }

    /**
     * Resolve the slug for a loot item, using the Blizzard API if necessary.
     */
    private function resolveItemSlug(BlizzardService $blizzard, Item $item): string
    {
        return Str::slug(Arr::get($blizzard->findItem($item->id), 'name') ?? "item-{$item->id}");
    }

    private function loadItemWithRelations(Item $item): void
    {
        $item->load([
            'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
            'raid',
            'boss',
        ]);
    }

    private function paginateComments(Item $item): LengthAwarePaginator
    {
        return $item->comments()->with('user')->latest()->paginate(10);
    }
}
