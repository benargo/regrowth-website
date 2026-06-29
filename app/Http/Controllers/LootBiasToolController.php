<?php

namespace App\Http\Controllers;

use App\Http\Resources\ItemResource;
use App\Http\Resources\RaidResource;
use App\Models\Boss;
use App\Models\Raid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    public function showRaid(Raid $raid, Request $request, ?string $name = null): Response|RedirectResponse
    {
        if (! $name) {
            return redirect()->route('loot.raids.show', ['raid' => $raid->id, 'name' => Str::slug($raid->name)], 303);
        }

        if (Str::slug($raid->name) !== $name) {
            return redirect()->route('loot.raids.show', ['raid' => $raid->id, 'name' => Str::slug($raid->name)], 303);
        }

        $raid->loadExists('trashItems');
        $raid->loadCount(['comments as trash_comments_count' => fn ($q) => $q->whereNull('items.boss_id')]);
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
