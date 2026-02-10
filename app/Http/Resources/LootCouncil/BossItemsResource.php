<?php

namespace App\Http\Resources\LootCouncil;

use App\Models\LootCouncil\Item;
use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class BossItemsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $itemService = app(ItemService::class);
        $mediaService = app(MediaService::class);

        return [
            'bossId' => $this->resource['bossId'],
            'items' => $this->resource['items']->map(function (Item $item) use ($itemService, $mediaService) {
                $blizzardData = $this->getBlizzardData($itemService, $item);
                $iconUrl = $this->getIconUrl($mediaService, $item);

                return [
                    'id' => $item->id,
                    'raid' => $this->getRelation($item, 'raid'),
                    'boss' => $this->getRelation($item, 'boss'),
                    'group' => $item->group,
                    'name' => $blizzardData['name'] ?? "Item #{$item->id}",
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
    protected function getBlizzardData(ItemService $itemService, Item $item): array
    {
        try {
            return $itemService->find($item->id);
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Get icon URL from Blizzard media API.
     */
    protected function getIconUrl(MediaService $mediaService, Item $item): ?string
    {
        try {
            $media = $mediaService->find('item', $item->id);
            $assets = $media['assets'] ?? [];

            if (empty($assets)) {
                return null;
            }

            $urls = $mediaService->getAssetUrls($assets);

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
