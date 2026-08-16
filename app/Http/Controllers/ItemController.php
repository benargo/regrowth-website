<?php

namespace App\Http\Controllers;

use App\Events\Broadcasts\ItemUpdated;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Middleware\EnsureItemSlugIsValid;
use App\Http\Middleware\RemembersOriginRaid;
use App\Http\Requests\Items\UpdateItemRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\PriorityResource;
use App\Models\Comment;
use App\Models\Item;
use App\Models\LootPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ItemController extends Controller
{
    /**
     * The number of replies eager-loaded per root, and the page size of each
     * subsequent "load more" request.
     */
    public const REPLIES_PER_PAGE = 5;

    /**
     * The maximum number of roots a single `replies` request may ask for.
     */
    public const MAX_REPLY_ROOTS = 50;

    /** Display a specific loot item. */
    #[Middleware(EnsureItemSlugIsValid::class)]
    #[Middleware(RemembersOriginRaid::class)]
    public function show(
        Request $request,
        BlizzardConnector $blizzardConnector,
        Item $item,
        ?string $slug = null
    ): InertiaResponse|RedirectResponse {
        $this->loadItemData($blizzardConnector, $item);

        return Inertia::render('Loot/Items/Show', [
            'item' => new ItemResource($item),
            'comments' => $this->buildCommentsProp($item),
            'replies' => Inertia::optional(fn () => $this->buildRepliesProp($item, $request)),
            'origin_raid_id' => $request->attributes->get('origin_raid_id'),
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
        $this->loadItemData($blizzardConnector, $item);

        return Inertia::render('Loot/Items/Edit', [
            'item' => new ItemResource($item),
            'priorities' => PriorityResource::collection(LootPriority::all()),
            'comments' => $this->buildCommentsProp($item),
            'replies' => Inertia::optional(fn () => $this->buildRepliesProp($item, $request)),
            'origin_raid_id' => $request->attributes->get('origin_raid_id'),
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
            'replies' => Inertia::optional(fn () => $this->buildRepliesProp($item, $request)),
        ]);
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
            'raids',
            'boss',
            'priorities' => fn (BelongsToMany $query) => $query->orderByPivot('weight', 'desc'),
        ]);
    }

    /**
     * Build the paginated top-level comments prop for an item.
     *
     * Only roots are listed. A trashed root is included when it still has live
     * replies, so its tombstone renders above a surviving discussion; a trashed
     * root with nothing under it is dropped entirely.
     */
    private function buildCommentsProp(Item $item): AnonymousResourceCollection
    {
        $comments = $item->comments()
            ->withTrashed()
            ->topLevel()
            ->where(fn (Builder $query) => $query
                ->whereNull('deleted_at')
                ->orWhereHas('replies')
            )
            ->withCount('replies')
            ->with(['user', 'reactions.user'])
            ->latest()
            ->paginate(10);

        $this->attachFirstRepliesPage($comments->getCollection());

        return CommentResource::collection($comments);
    }

    /**
     * Attach each root's first page of replies.
     *
     * A constrained eager load cannot LIMIT per parent — `limit()` inside the
     * closure caps the whole result set, not each root's slice. A page holds at
     * most 10 roots, so a loop of small indexed queries is simpler than a
     * window-function query and cheap enough at this size.
     *
     * @param  Collection<int, Comment>  $roots
     */
    private function attachFirstRepliesPage(Collection $roots): void
    {
        $roots->each(function (Comment $root): void {
            $root->setRelation('replies', $root->replies()
                ->with(['user', 'reactions.user'])
                ->limit(self::REPLIES_PER_PAGE)
                ->get());
        });
    }

    /**
     * Build the next page of replies for the requested roots, keyed by root id.
     *
     * `offsets` maps root id to how many replies the client already holds, so
     * each root resumes from its own position and several remembered threads
     * restore in a single request. The query starts from the item's own
     * comments, so a root belonging to another commentable simply matches
     * nothing — the page's authorization is the only check needed.
     *
     * Offsets are client-supplied and may be stale: unknown ids, non-roots, and
     * fully-loaded roots yield no entry rather than an error.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildRepliesProp(Item $item, Request $request): array
    {
        $offsets = collect($request->input('offsets', []))
            ->mapWithKeys(fn (mixed $offset, mixed $rootId): array => [(int) $rootId => max(0, (int) $offset)])
            ->take(self::MAX_REPLY_ROOTS);

        if ($offsets->isEmpty()) {
            return [];
        }

        return $offsets
            ->map(fn (int $offset, int $rootId) => $item->comments()
                ->where('parent_id', $rootId)
                ->with(['user', 'reactions.user'])
                ->oldest()
                ->skip($offset)
                ->take(self::REPLIES_PER_PAGE)
                ->get())
            ->filter(fn (Collection $replies): bool => $replies->isNotEmpty())
            ->map(fn (Collection $replies) => CommentResource::collection($replies)->resolve($request))
            ->all();
    }
}
