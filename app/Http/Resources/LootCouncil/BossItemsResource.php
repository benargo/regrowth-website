<?php

namespace App\Http\Resources\LootCouncil;

use App\Models\LootCouncil\Item;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BossItemsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $blizzard = app(BlizzardService::class);
        $media = app(MediaService::class);

        return [
            'bossId' => $this->resource['bossId'],
            'items' => $this->resource['items']->map(function (Item $item) use ($blizzard, $media) {
                $blizzardData = $this->getBlizzardData($blizzard, $item);
                $iconUrl = $this->getIconUrl($blizzard, $media, $item);

                return [
                    'id' => $item->id,
                    'raid' => $this->getRelation($item, 'raid'),
                    'boss' => $this->getRelation($item, 'boss'),
                    'group' => $item->group,
                    'name' => $blizzardData['name'] ?? "Item #{$item->id}",
                    'slug' => Str::slug($blizzardData['name'] ?? "item-{$item->id}"),
                    'icon' => $iconUrl,
                    'priorities' => PriorityResource::collection($item->getRelation('priorities')),
                    'hasNotes' => $item->notes !== null,
                    'commentsCount' => $item->comments_count,
                    'wowhead_url' => $this->getWowheadUrl($item),
                ];
            })->toArray(),
            'commentsCount' => $this->resource['items']->sum('comments_count'),
        ];
    }

    /**
     * Get a related model's data if it's loaded, otherwise return the foreign key ID.
     *
     * @return array<string, mixed>|int
     */
    protected function getRelation(Item $item, string $relation)
    {
        if (! $item->relationLoaded($relation)) {
            return $item->{"{$relation}_id"};
        }

        return $item->{$relation};
    }

    /**
     * Get item data from Blizzard API.
     *
     * @return array<string, mixed>
     */
    protected function getBlizzardData(BlizzardService $blizzard, Item $item): array
    {
        try {
            return $blizzard->findItem($item->id);
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Get icon URL from Blizzard media API.
     */
    protected function getIconUrl(BlizzardService $blizzard, MediaService $media, Item $item): ?string
    {
        try {
            $mediaData = $blizzard->findMedia('item', $item->id);
            $assets = Arr::get($mediaData, 'assets', []);

            if (empty($assets)) {
                return null;
            }

            $urls = $media->get($assets);

            return array_values($urls)[0] ?? null;
        } catch (\Exception) {
            return null;
        }
    }

    protected function getWowheadUrl(Item $item): string
    {
        $baseUrl = 'https://www.wowhead.com/tbc/item=';

        return $baseUrl.$item->id.($item->name ? '/'.Str::slug($item->name) : '');
    }
}
