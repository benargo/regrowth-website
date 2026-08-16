<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RemembersCurrentRaid;
use App\Http\Resources\ItemResource;
use App\Http\Resources\RaidResource;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\Item;
use App\Models\ItemPriority;
use App\Models\Raid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LootBiasToolController extends Controller
{
    /** @var int */
    private const PRIORITY_WEIGHT_THRESHOLD = 2;

    public function index(): Response
    {
        $raids = RaidResource::collection(
            Raid::with('phase')->orderBy('phase_id')->orderBy('id')->get()
        );

        return Inertia::render('Loot/Index', [
            'raids' => $raids,
            'stats' => Cache::tags(['lootcouncil'])->remember('loot:stats', now()->addMinutes(10), function () {
                return [
                    'items_count' => Item::count(),
                    'priority_rows_count' => ItemPriority::query()->distinct()->count(['item_id', 'weight']),
                    'comments_count' => Comment::count(),
                    'commenters_count' => Comment::distinct('user_id')->count('user_id'),
                    'reactions_count' => CommentReaction::count(),
                ];
            }),
        ]);
    }

    #[Middleware(RemembersCurrentRaid::class)]
    public function showRaid(Raid $raid, Request $request, ?string $name = null): Response|RedirectResponse
    {
        if (! $name) {
            return redirect()->route('loot.raids.show', ['raid' => $raid->id, 'name' => Str::slug($raid->name)], 303);
        }

        if (Str::slug($raid->name) !== $name) {
            return redirect()->route('loot.raids.show', ['raid' => $raid->id, 'name' => Str::slug($raid->name)], 303);
        }

        $raid->loadExists('trashItems');
        $raid->trash_comments_count = Comment::query()
            ->where('commentable_type', Item::class)
            ->whereIn('commentable_id', $raid->trashItems()->select('items.id'))
            ->count();
        $raid->load(['bosses' => fn ($q) => $q->orderBy('encounter_order')->withCount('comments')]);

        return Inertia::render('Loot/Raids/Show', [
            'raid' => new RaidResource($raid),
            'priority_weight_threshold' => self::PRIORITY_WEIGHT_THRESHOLD,
            'boss_items' => $raid->bosses->mapWithKeys(function (Boss $boss) {
                return [$boss->id => Inertia::optional(function () use ($boss) {
                    return ItemResource::collection(
                        $boss->items()
                            ->withCount('comments')
                            ->with('priorities')
                            ->get()
                    );
                })];
            })->all(),
            'trash_items' => Inertia::optional(function () use ($raid) {
                if ($raid->trash_items_exists) {
                    return ItemResource::collection(
                        $raid->trashItems()
                            ->withCount('comments')
                            ->with('priorities')
                            ->get()
                    );
                }
            }),
        ]);
    }
}
