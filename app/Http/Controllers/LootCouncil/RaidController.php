<?php

namespace App\Http\Controllers\LootCouncil;

use App\Http\Controllers\Controller;
use App\Http\Resources\LootCouncil\BossItemsResource;
use App\Models\Boss;
use App\Models\LootCouncil\Item;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RaidController extends Controller
{
    /**
     * Display the loot council view for a specific raid, including its bosses and items.
     */
    public function show(Raid $raid, Request $request, ?string $name = null): InertiaResponse|RedirectResponse
    {
        // If no name is provided in the URL, redirect to the URL with the raid name slug for better SEO and user experience.
        if (! $name) {
            return redirect()->route('loot.raids.show', ['raid' => $raid->id, 'name' => Str::slug($raid->name)], 303);
        }

        // If a name is provided in the URL, ensure it matches the slug of the raid's name. If not, redirect to the correct URL.
        if ($name && Str::slug($raid->name) !== $name) {
            return redirect()->route('loot.raids.show', ['raid' => $raid->id, 'name' => Str::slug($raid->name)]);
        }

        // Store the last visited raid for the raid's phase in the session, so we can redirect back to it when visiting the phase overview.
        $request->session()->put("loot.last_visited_raid.{$raid->phase_id}", $raid->id);

        // Preload phases to ensure we have the latest data for the active raid's phase (in case it was just switched)
        $phases = Phase::hydrate(
            Cache::tags(['db', 'lootcouncil'])->remember('phases:with_raids', now()->addYear(), function () {
                return Phase::with('raids')->get()->toArray();
            })
        );

        return Inertia::render('LootBiasTool/Raid', [
            'phases' => $phases,
            'selected_phase_id' => $raid->phase_id,
            'selected_raid_id' => (int) $raid->id,
            'bosses' => Inertia::defer(fn () => $this->getBossesForRaid($raid)),
            // Only load boss items when explicitly requested via partial reload
            'boss_items' => Inertia::optional(fn () => $this->getItemsForBoss(
                $request->integer('boss_id')
            )),
        ]);
    }

    /**
     * Get raids for a specific phase, with caching.
     *
     * @return EloquentCollection<Raid>
     */
    private function getRaidsForPhase(Phase $phase): EloquentCollection
    {
        return Raid::hydrate(
            Cache::tags(['db', 'lootcouncil'])->remember("phases:#{$phase->id}:raids", now()->addYear(), function () use ($phase) {
                return $phase->raids()->get()->toArray();
            })
        );
    }

    /**
     * Get bosses for a specific raid, with caching.
     *
     * @return EloquentCollection<Boss>
     */
    private function getBossesForRaid(Raid $raid): EloquentCollection
    {
        return Boss::hydrate(
            Cache::tags(['db', 'lootcouncil'])->remember("raids:#{$raid->id}:bosses", now()->addMonth(), function () use ($raid) {
                $bosses = $raid->bosses()
                    ->orderBy('encounter_order')
                    ->withCount('comments')
                    ->get();

                if ($raid->trashItems()->exists()) {
                    $bosses->push([
                        'id' => -1 * $raid->id,
                        'raid_id' => $raid->id,
                        'name' => 'Trash drops',
                        'encounter_order' => 999,
                        'comments_count' => $raid->comments()->whereNull('lootcouncil_items.boss_id')->count(),
                    ]);
                }

                return $bosses->toArray();
            })
        );
    }

    /**
     * Get items for a specific boss.
     */
    private function getItemsForBoss(?int $bossId): array
    {
        if (! $bossId) {
            return (new BossItemsResource([
                'bossId' => null,
                'items' => collect(),
                'comments_count' => 0,
            ]))->response(request())->getData(true);
        }

        if ($bossId < 0) {
            // Trash boss IDs are negative raid IDs (-1 * raidId)
            $raidId = abs($bossId);

            return $this->getTrashItemsForRaid($raidId);
        }

        return Cache::tags(['db', 'lootcouncil'])->remember(
            "boss:#{$bossId}:items",
            now()->addWeek(),
            function () use ($bossId) {
                $items = Item::query()
                    ->where('boss_id', $bossId)
                    ->with([
                        'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
                    ])
                    ->withCount('comments')
                    ->get();

                return (new BossItemsResource([
                    'bossId' => $bossId,
                    'items' => $items,
                    'commentsCount' => $items->sum('comments_count'),
                ]))->response(request())->getData(true);
            }
        );
    }

    /**
     * Get trash items for a specific raid.
     *
     * Since trash items are not associated with a specific boss, we use a special negative boss ID convention to cache and retrieve them.
     */
    protected function getTrashItemsForRaid(?int $raidId = null): array
    {
        if (! $raidId) {
            $raidId = 1;
        }

        return Cache::tags(['db', 'lootcouncil'])->remember("raid:#{$raidId}:trash_items", now()->addWeek(), function () use ($raidId) {
            $items = Item::query()
                ->where('raid_id', $raidId)
                ->whereNull('boss_id')
                ->with([
                    'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
                ])
                ->withCount('comments')
                ->get();

            return (new BossItemsResource([
                'bossId' => -1 * $raidId,
                'items' => $items,
                'commentsCount' => $items->sum('comments_count'),
            ]))->response(request())->getData(true);
        });
    }
}
