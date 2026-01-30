<?php

namespace App\Http\Resources\LootCouncil;

use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $itemService = app(ItemService::class);
        $mediaService = app(MediaService::class);
        $blizzardData = $this->getBlizzardData($itemService);
        $iconUrl = $this->getIconUrl($mediaService);

        return [
            'id' => $this->id,
            'raid' => $this->getRaidData(),
            'boss' => $this->getBossData(),
            'group' => $this->group,
            'name' => $blizzardData['name'] ?? "Item #{$this->id}",
            'icon' => $iconUrl,
            'item_class' => $blizzardData['item_class']['name'] ?? null,
            'item_subclass' => $blizzardData['item_subclass']['name'] ?? null,
            'quality' => $blizzardData['quality'] ?? null,
            'inventory_type' => $blizzardData['inventory_type']['name'] ?? null,
            'priorities' => PriorityResource::collection($this->whenLoaded('priorities')),
            'notes' => $this->notes,
            'wowhead_url' => $this->getWowheadUrl($blizzardData['name'] ?? null),
        ];
    }

    public function getRaidData()
    {
        if (! $this->relationLoaded('raid')) {
            return $this->raid_id;
        }

        return $this->raid;
    }

    public function getBossData()
    {
        if (! $this->relationLoaded('boss')) {
            return $this->boss_id;
        }

        return $this->boss;
    }

    /**
     * Get item data from Blizzard API.
     *
     * @return array<string, mixed>
     */
    protected function getBlizzardData(ItemService $itemService): array
    {
        try {
            return $itemService->find($this->id);
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Get icon URL from Blizzard media API.
     */
    protected function getIconUrl(MediaService $mediaService): ?string
    {
        try {
            $media = $mediaService->find('item', $this->id);
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

    protected function getWowheadUrl(?string $name = null): string
    {
        $baseUrl = 'https://www.wowhead.com/tbc/item=';

        return $baseUrl.$this->id.($name ? '/'.Str::slug($name) : '');
    }
}
