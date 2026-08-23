<?php

namespace App\Http\Controllers;

use App\Contracts\Http\Middleware\SharesOriginRaidSession;
use App\Events\Broadcasts\ItemUpdated;
use App\Events\Broadcasts\LootPrioritiesChanged;
use App\Http\Controllers\Concerns\PaginatesCommentReplies;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Middleware\EnsureItemSlugIsValid;
use App\Http\Middleware\RemembersOriginRaid;
use App\Http\Requests\Items\UpdateItemRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\PriorityResource;
use App\Models\Item;
use App\Models\LootPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ItemController extends Controller
{
    use PaginatesCommentReplies;

    /**
     * The item whose comments are being paginated.
     *
     * Set by each action before the `replies` prop closure is evaluated. Inertia
     * resolves `Inertia::optional()` during response rendering, by which point
     * the action has returned, so the item cannot be passed as an argument.
     */
    private ?Item $commentableItem = null;

    /** Display a specific loot item. */
    #[Middleware(EnsureItemSlugIsValid::class)]
    #[Middleware(RemembersOriginRaid::class)]
    public function show(
        Request $request,
        BlizzardConnector $blizzardConnector,
        Item $item,
        ?string $slug = null
    ): InertiaResponse|RedirectResponse {
        $this->loadItemData($blizzardConnector, $item, $request->attributes->get(SharesOriginRaidSession::SESSION_KEY));

        return Inertia::render('Loot/Items/Show', [
            'item' => new ItemResource($item),
            'comments' => $this->buildCommentsProp($item),
            'replies' => Inertia::optional(fn () => $this->buildRepliesProp($request)),
        ]);
    }

    /** Show the form for editing a specific loot item. */
    #[Middleware('auth')]
    #[Middleware(EnsureItemSlugIsValid::class)]
    #[Middleware(RemembersOriginRaid::class)]
    #[Authorize('update', 'item')]
    public function edit(
        Request $request,
        BlizzardConnector $blizzardConnector,
        Item $item,
        ?string $slug = null
    ): InertiaResponse|RedirectResponse {
        $this->loadItemData($blizzardConnector, $item, $request->attributes->get(SharesOriginRaidSession::SESSION_KEY));

        return Inertia::render('Loot/Items/Edit', [
            'item' => new ItemResource($item),
            'priorities' => PriorityResource::collection(LootPriority::all()),
            'comments' => $this->buildCommentsProp($item),
            'replies' => Inertia::optional(fn () => $this->buildRepliesProp($request)),
        ]);
    }

    /** Update a loot item's notes and biases. */
    #[Middleware('auth')]
    #[Middleware(RemembersOriginRaid::class)]
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

        $this->loadItemRelations($item, $request->attributes->get(SharesOriginRaidSession::SESSION_KEY));

        broadcast(new ItemUpdated($item))->toOthers();

        if ($request->has('priorities')) {
            broadcast(new LootPrioritiesChanged)->toOthers();
        }

        return Inertia::render('Loot/Items/Edit', [
            'item' => new ItemResource($item),
            'priorities' => PriorityResource::collection(LootPriority::all()),
            'comments' => $this->buildCommentsProp($item),
            'replies' => Inertia::optional(fn () => $this->buildRepliesProp($request)),
        ]);
    }

    private function loadItemData(BlizzardConnector $blizzardConnector, Item $item, ?int $originRaidId): void
    {
        try {
            $blizzardItem = $blizzardConnector->send(new GetItemRequest($item->id))->dto();
            $item->fillBlizzardData($blizzardItem);
        } catch (ItemNotFoundException) {
            // We can continue without the filled in data.
        }

        $this->loadItemRelations($item, $originRaidId);
    }

    /**
     * Eager-load an item's relations, keeping at most one raid.
     *
     * When $originRaidId is given, load only the matching raid (the user's
     * remembered origin raid). Otherwise load only the first raid by id, so
     * the frontend always receives a single, deterministic raid rather than
     * every raid a cross-raid trash item drops in.
     */
    private function loadItemRelations(Item $item, ?int $originRaidId): void
    {
        $item->load([
            'raids' => fn (BelongsToMany $query) => $query
                ->when(
                    $originRaidId,
                    fn (Builder $q) => $q->where('raids.id', $originRaidId),
                    fn (Builder $q) => $q->orderBy('raids.id')->limit(1),
                ),
            'boss',
            'priorities' => fn (BelongsToMany $query) => $query->orderByPivot('weight', 'desc'),
        ]);
    }

    protected function repliesBaseQuery(): Builder
    {
        return $this->commentableItem->comments()->getQuery();
    }

    /**
     * @return array<int, string>
     */
    protected function replyEagerLoads(): array
    {
        return ['user', 'reactions.user'];
    }

    /**
     * Build the paginated top-level comments prop for an item.
     */
    private function buildCommentsProp(Item $item): AnonymousResourceCollection
    {
        $this->commentableItem = $item;

        $comments = $item->comments()
            ->listableRoots()
            ->withCount('replies')
            ->with($this->replyEagerLoads())
            ->latest()
            ->paginate(10);

        $this->attachFirstRepliesPage($comments->getCollection());

        return CommentResource::collection($comments);
    }
}
