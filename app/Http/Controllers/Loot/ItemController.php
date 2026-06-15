<?php

namespace App\Http\Controllers\Loot;

use App\Http\Controllers\Controller;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Requests\Items\UpdateItemNotesRequest;
use App\Http\Requests\Items\UpdateItemPrioritiesRequest;
use App\Http\Resources\ItemResource;
use App\Http\Resources\LootCouncil\CommentResource;
use App\Http\Resources\LootCouncil\PriorityResource;
use App\Models\Item;
use App\Models\LootCouncil\Priority;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ItemController extends Controller
{
    /** Display a specific loot item. */
    public function show(BlizzardConnector $blizzard, Request $request, Item $item, ?string $name = null): InertiaResponse|RedirectResponse
    {
        $slug = $this->resolveItemSlug($blizzard, $item);

        if ($name !== $slug) {
            return redirect()->route('loot.items.show', ['item' => $item->id, 'name' => $slug], 303);
        }

        $this->loadItemWithRelations($item);

        return Inertia::render('Loot/Items/Show', [
            'item' => new ItemResource($item),
            'comments' => CommentResource::collection($this->paginateComments($item)),
        ]);
    }

    /** Show the form for editing a specific loot item. */
    #[Middleware('auth')]
    #[Authorize('update', 'item')]
    public function edit(BlizzardConnector $blizzard, Request $request, Item $item, ?string $name = null): InertiaResponse|RedirectResponse
    {
        $slug = $this->resolveItemSlug($blizzard, $item);

        if ($name !== $slug) {
            return redirect()->route('loot.items.edit', ['item' => $item->id, 'name' => $slug], 303);
        }

        $this->loadItemWithRelations($item);

        $allPriorities = Priority::hydrate(
            Cache::tags(['db', 'lootcouncil'])->remember('priorities:all', now()->addYear(), fn () => Priority::all()->map->getAttributes()->toArray())
        );

        return Inertia::render('Loot/Items/Edit', [
            'item' => new ItemResource($item),
            'allPriorities' => PriorityResource::collection($allPriorities),
            'comments' => CommentResource::collection($this->paginateComments($item)),
        ]);
    }

    /** Redirect to the edit page for a specific loot item. */
    #[Middleware('auth')]
    #[Authorize('update', 'item')]
    public function redirectToEdit(BlizzardConnector $blizzard, Item $item): RedirectResponse
    {
        return redirect()->route('loot.items.edit', ['item' => $item->id, 'name' => $this->resolveItemSlug($blizzard, $item)], 303);
    }

    /** Update the officers' notes for a specific loot item. */
    #[Middleware('auth')]
    #[Authorize('update', 'item')]
    public function updateNotes(UpdateItemNotesRequest $request, Item $item): RedirectResponse
    {
        $item->notes = $request->validated('notes');
        $item->save();

        return redirect()->back();
    }

    /** Update the priorities for a specific loot item. */
    #[Middleware('auth')]
    #[Authorize('update', 'item')]
    public function updatePriorities(UpdateItemPrioritiesRequest $request, Item $item): RedirectResponse
    {
        $priorities = collect($request->validated('priorities'))
            ->mapWithKeys(fn ($p) => [$p['priority_id'] => ['weight' => $p['weight']]])
            ->all();

        $item->priorities()->sync($priorities);

        return redirect()->back();
    }

    /** Resolve the slug for a loot item, using the Blizzard API if necessary. */
    private function resolveItemSlug(BlizzardConnector $blizzard, Item $item): string
    {
        try {
            return Str::slug($blizzard->send(new GetItemRequest($item->id))->dto()->name);
        } catch (ItemNotFoundException) {
            return "item-{$item->id}";
        }
    }

    /** Load the item's priorities, raid, and boss relationships. */
    private function loadItemWithRelations(Item $item): void
    {
        $item->load([
            'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
            'raid',
            'boss',
        ]);
    }

    /** Return a paginated, chronological collection of the item's comments with their authors. */
    private function paginateComments(Item $item): LengthAwarePaginator
    {
        return $item->comments()->with('user')->latest()->paginate(10);
    }
}
