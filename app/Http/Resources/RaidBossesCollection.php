<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\ResourceCollection;

#[Collects(BossResource::class)]
class RaidBossesCollection extends ResourceCollection
{
    public static $wrap = null;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection
                ->groupBy(fn (BossResource $boss) => (string) $boss->raid_id)
                ->map(fn ($group) => $group->map(fn (BossResource $boss) => $boss->resolve($request))->values()),
            'raid_ids' => $this->collection->pluck('raid_id')->unique()->values()->all(),
        ];
    }
}
