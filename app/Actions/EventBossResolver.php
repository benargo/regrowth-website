<?php

namespace App\Actions;

use App\Http\Integrations\RaidHelper\Data\Zones\ZoneData;
use App\Models\Boss;
use App\Models\Raid;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the ordered set of bosses an event should hold.
 *
 * Both the RaidHelper sync and the event template controller need the same
 * answer to "given these raids in this order, which bosses belong to the event
 * and in what sequence?" — keeping it here stops the two from drifting.
 */
class EventBossResolver
{
    /**
     * Resolve the bosses selected by a zone payload, in payload order.
     *
     * A zone whose `bosses` key is absent contributes every boss in its raid.
     * An explicit list contributes only those bosses that actually belong to
     * the raid; ids naming a boss from elsewhere are skipped and logged.
     *
     * @param  Collection<int, ZoneData>  $zones
     * @param  Collection<int, Raid>  $raids  the resolved raids, keyed by id
     * @return Collection<int, Boss>
     */
    public function fromZones(Collection $zones, Collection $raids): Collection
    {
        return $zones
            ->filter(fn (ZoneData $zone): bool => $raids->has($zone->id))
            ->flatMap(fn (ZoneData $zone): Collection => $this->bossesForZone($zone))
            ->values();
    }

    /**
     * Resolve every boss belonging to the given raids, in raid order.
     *
     * @param  Collection<int, int>  $raidIds  raid ids in the desired order
     * @return Collection<int, Boss>
     */
    public function fromRaidIds(Collection $raidIds): Collection
    {
        $bosses = Boss::whereIn('raid_id', $raidIds)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('raid_id');

        return $raidIds
            ->flatMap(fn (int $raidId): Collection => $bosses->get($raidId, collect()))
            ->values();
    }

    /**
     * @return Collection<int, Boss>
     */
    private function bossesForZone(ZoneData $zone): Collection
    {
        if ($zone->bosses === null) {
            return Boss::where('raid_id', $zone->id)->orderBy('sort_order')->get();
        }

        $requested = collect($zone->bosses);

        $resolved = Boss::where('raid_id', $zone->id)
            ->whereIn('id', $requested->pluck('id'))
            ->get()
            ->keyBy('id');

        $requested->pluck('id')->diff($resolved->keys())->each(
            fn (int $bossId) => Log::error('EventBossResolver: skipping boss that does not belong to the zone.', [
                'zone_id' => $zone->id,
                'boss_id' => $bossId,
            ])
        );

        return $requested
            ->filter(fn ($boss): bool => $resolved->has($boss->id))
            ->sortBy(fn ($boss, int $index): array => [
                $boss->order ?? $resolved[$boss->id]->sort_order,
                $index,
            ])
            ->map(fn ($boss): Boss => $resolved[$boss->id])
            ->values();
    }
}
