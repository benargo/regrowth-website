<?php

namespace App\Http\Controllers;

use App\Events\Broadcasts\ItemUpdated;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Requests\Items\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Http\Resources\LootCouncil\CommentResource;
use App\Http\Resources\LootCouncil\PriorityResource;
use App\Models\Item;
use App\Models\LootPriority;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ItemController extends Controller
{
    /** Display a specific loot item. */
    public function show(
        BlizzardConnector $blizzardConnector,
        Item $item,
        ?string $slug = null
    ): InertiaResponse|RedirectResponse {
        if ($redirect = $this->redirectForSlugMismatch($item, $slug, 'loot.items.show')) {
            return $redirect;
        }

        $this->loadItemData($blizzardConnector, $item);

        return Inertia::render('Loot/Items/Show', [
            'item' => new ItemResource($item),
            'comments' => $this->buildCommentsProp($item),
        ]);
    }

    /** Show the form for editing a specific loot item. */
    #[Middleware('auth')]
    #[Authorize('update', 'item')]
    public function edit(
        BlizzardConnector $blizzardConnector,
        Item $item,
        ?string $slug = null
    ): InertiaResponse|RedirectResponse {
        if ($redirect = $this->redirectForSlugMismatch($item, $slug, 'loot.items.edit')) {
            return $redirect;
        }

        $this->loadItemData($blizzardConnector, $item);

        return Inertia::render('Loot/Items/Edit', [
            'item' => new ItemResource($item),
            'priorities' => PriorityResource::collection(LootPriority::all()),
            'comments' => $this->buildCommentsProp($item),
        ]);
    }

    /** Update a loot item's notes and biases. */
    #[Middleware('auth')]
    #[Authorize('update', 'item')]
    public function update(UpdateItemRequest $request, Item $item): InertiaResponse
    {
        DB::transaction(function () use ($request, $item): void {
            if ($request->has('notes')) {
                $item->notes = $request->validated('notes');
                $item->save();
            }

            if ($request->has('priorities')) {
                $priorities = collect($request->validated('priorities'))
                    ->mapWithKeys(fn (array $priority) => [
                        $priority['priority_id'] => ['weight' => $priority['weight']],
                    ])
                    ->all();

                $item->priorities()->sync($priorities);
            }
        });

        $this->loadItemRelations($item);

        broadcast(new ItemUpdated($item))->toOthers();

        return Inertia::render('Loot/Items/Edit', [
            'item' => new ItemResource($item),
            'priorities' => PriorityResource::collection(LootPriority::all()),
            'comments' => $this->buildCommentsProp($item),
        ]);
    }

    private function redirectForSlugMismatch(Item $item, ?string $slug, string $routeName): ?RedirectResponse
    {
        $correctSlug = $item->slug ?: "item-{$item->id}";

        if ($correctSlug === $slug) {
            return null;
        }

        return redirect()->route($routeName, ['item' => $item->id, 'slug' => $correctSlug], 303);
    }

    private function loadItemData(BlizzardConnector $blizzardConnector, Item $item): void
    {
        try {
            $blizzardItem = $blizzardConnector->send(new GetItemRequest($item->id))->dto();
            $item->fillBlizzardData($blizzardItem);
        } catch (ItemNotFoundException) {
            // We can continue without the filled in data.
        }

        $this->loadItemRelations($item);
    }

    private function loadItemRelations(Item $item): void
    {
        $item->load([
            'raid',
            'boss',
            'priorities' => fn (BelongsToMany $query) => $query->orderByPivot('weight', 'desc'),
        ]);
    }

    private function buildCommentsProp(Item $item): AnonymousResourceCollection
    {
        return CommentResource::collection(
            $item->comments()->with('user')->latest()->paginate(10),
        );
    }
}
