<?php

namespace App\Http\Controllers\Loot;

use App\Http\Controllers\Controller;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Resources\BossResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\LootCouncil\CommentResource;
use App\Http\Resources\LootCouncil\PriorityResource;
use App\Http\Resources\RaidResource;
use App\Models\Item;
use App\Models\LootPriority;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ItemController extends Controller
{
    /** Display a specific loot item. */
    public function show(
        BlizzardConnector $blizzardConnector,
        Request $request,
        Item $item,
        ?string $slug = null
    ): InertiaResponse|RedirectResponse {
        $correctSlug = $item->slug ?: "item-{$item->id}";

        if ($correctSlug !== $slug) {
            return redirect()->route('loot.items.show', ['item' => $item->id, 'slug' => $correctSlug], 303);
        }

        try {
            $blizzardItem = $blizzardConnector->send(new GetItemRequest($item->id))->dto();
            $item->fillBlizzardData($blizzardItem);
        } catch (ItemNotFoundException) {
            // We can continue without the filled in data.
        }

        $item->load(['priorities' => fn ($q) => $q->orderByPivot('weight', 'desc')]);

        return Inertia::render('Loot/Items/Show', [
            'raid' => new RaidResource($item->raid()->first()),
            'boss' => new BossResource($item->boss()->first()),
            'item' => new ItemResource($item),
            'comments' => CommentResource::collection($item->comments()->with('user')->latest()->paginate(10)),
        ]);
    }

    /** Show the form for editing a specific loot item. */
    #[Middleware('auth')]
    #[Authorize('update', 'item')]
    public function edit(
        BlizzardConnector $blizzardConnector,
        Request $request,
        Item $item,
        ?string $slug = null
    ): InertiaResponse|RedirectResponse {
        $correctSlug = $item->slug ?: "item-{$item->id}";

        if ($correctSlug !== $slug) {
            return redirect()->route('loot.items.edit', ['item' => $item->id, 'slug' => $correctSlug], 303);
        }

        try {
            $blizzardItem = $blizzardConnector->send(new GetItemRequest($item->id))->dto();
            $item->fillBlizzardData($blizzardItem);
        } catch (ItemNotFoundException) {
            // We can continue without the filled in data.
        }

        $item->load(['priorities' => fn ($q) => $q->orderByPivot('weight', 'desc')]);

        return Inertia::render('Loot/Items/Edit', [
            'raid' => new RaidResource($item->raid()->first()),
            'item' => new ItemResource($item),
            'allPriorities' => PriorityResource::collection(LootPriority::all()),
            'comments' => CommentResource::collection($item->comments()->with('user')->latest()->paginate(10)),
        ]);
    }
}
