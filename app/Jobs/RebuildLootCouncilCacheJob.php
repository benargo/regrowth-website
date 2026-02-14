<?php

namespace App\Jobs;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Models\TBC\Boss;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RebuildLootCouncilCacheJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    public function __construct()
    {
        //
    }

    /**
     * Prevent overlapping rebuilds.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('lootcouncil-cache-rebuild'))->dontRelease(),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->rebuildPrioritiesCache();
        $this->rebuildBossesCache();
        $this->rebuildBossItemsCaches();
        $this->rebuildTrashItemsCaches();

        Log::info('LootCouncil cache rebuilt successfully.');
    }

    public function rebuildPrioritiesCache(): void
    {
        Cache::tags(['lootcouncil'])->remember('priorities.all', now()->addYear(), fn () => Priority::all());

        Log::info('LootCouncil priorities cache rebuilt.');
    }

    /**
     * Rebuild the bosses.tbc.with_comments cache.
     */
    protected function rebuildBossesCache(): void
    {
        Cache::tags(['lootcouncil'])->remember(
            'bosses.tbc.with_comments',
            now()->addWeek(),
            function () {
                $bosses = Boss::query()
                    ->orderBy('encounter_order')
                    ->get();

                $commentCounts = Comment::query()
                    ->join('lootcouncil_items', 'lootcouncil_items.id', '=', 'lootcouncil_comments.item_id')
                    ->whereNotNull('lootcouncil_items.boss_id')
                    ->selectRaw('lootcouncil_items.boss_id, count(*) as comments_count')
                    ->groupBy('lootcouncil_items.boss_id')
                    ->pluck('comments_count', 'boss_id');

                $bosses->each(fn ($boss) => $boss->comments_count = $commentCounts->get($boss->id, 0));

                Item::query()
                    ->whereNull('boss_id')
                    ->whereNotNull('raid_id')
                    ->distinct()
                    ->pluck('raid_id')
                    ->each(function ($trashRaidId) use ($bosses) {
                        $trashBoss = new Boss([
                            'id' => -1 * $trashRaidId,
                            'raid_id' => $trashRaidId,
                            'name' => 'Trash drops',
                            'encounter_order' => 999,
                        ]);
                        $trashBoss->comments_count = 0;
                        $bosses->push($trashBoss);
                    });

                return $bosses->groupBy('raid_id')->toArray();
            }
        );
    }

    /**
     * Rebuild cache for all boss items.
     */
    protected function rebuildBossItemsCaches(): void
    {
        $bossIds = Boss::query()->pluck('id');

        foreach ($bossIds as $bossId) {
            Cache::tags(['lootcouncil'])->remember(
                "loot_items.boss_{$bossId}.index",
                now()->addDays(7),
                fn () => Item::query()
                    ->where('boss_id', $bossId)
                    ->with([
                        'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
                    ])
                    ->withCount('comments')
                    ->get()
            );
        }
    }

    /**
     * Rebuild cache for all trash items (items without a boss).
     */
    protected function rebuildTrashItemsCaches(): void
    {
        $raidIds = Item::query()
            ->whereNull('boss_id')
            ->whereNotNull('raid_id')
            ->distinct()
            ->pluck('raid_id');

        foreach ($raidIds as $raidId) {
            Cache::tags(['lootcouncil'])->remember(
                "loot_items.trash_raid_{$raidId}.index",
                now()->addWeek(),
                fn () => Item::query()
                    ->where('raid_id', $raidId)
                    ->whereNull('boss_id')
                    ->with([
                        'priorities' => fn ($q) => $q->orderByPivot('weight', 'desc'),
                    ])
                    ->withCount('comments')
                    ->get()
            );
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('RebuildLootCouncilCacheJob failed.', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
