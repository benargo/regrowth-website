<?php

namespace App\Http\Resources\LootCouncil;

use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
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
        $blizzard = app(BlizzardService::class);
        $media = app(MediaService::class);
        $blizzardData = $this->getBlizzardData($blizzard);
        $iconUrl = $this->getIconUrl($blizzard, $media);

        return [
            'id' => $this->id,
            'raid' => $this->getRelation('raid'),
            'boss' => $this->getRelation('boss'),
            'group' => $this->group,
            'name' => $blizzardData['name'] ?? "Item #{$this->id}",
            'slug' => Str::slug($blizzardData['name'] ?? "item-{$this->id}"),
            'icon' => $iconUrl,
            'item_class' => $blizzardData['item_class']['name'] ?? null,
            'item_subclass' => $blizzardData['item_subclass']['name'] ?? null,
            'quality' => $blizzardData['quality'] ?? null,
            'inventory_type' => $blizzardData['inventory_type']['name'] ?? null,
            'priorities' => PriorityResource::collection($this->whenLoaded('priorities')),
            'comments_count' => $this->whenCounted('comments'),
            'notes' => $this->notes,
            'wowhead_url' => $this->getWowheadUrl($blizzardData['name'] ?? null),
        ];
    }

    /**
     * Get a related model's data if it's loaded, otherwise return the foreign key ID.
     *
     * @return array<string, mixed>|string|int
     */
    protected function getRelation(string $relation): mixed
    {
        if (! $this->relationLoaded($relation)) {
            return $this->{"{$relation}_id"};
        }

        return match ($relation) {
            'raid' => $this->raid,
            'boss' => $this->boss,
            default => $this->{$relation},
        };
    }

    /**
     * Get item data from Blizzard API.
     *
     * @return array<string, mixed>
     */
    protected function getBlizzardData(BlizzardService $blizzard): array
    {
        try {
            return $blizzard->findItem($this->id);
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Get icon URL from Blizzard media API.
     */
    protected function getIconUrl(BlizzardService $blizzard, MediaService $media): ?string
    {
        try {
            $mediaData = $blizzard->findMedia('item', $this->id);
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

    protected function getWowheadUrl(?string $name = null): string
    {
        $baseUrl = 'https://www.wowhead.com/tbc/item=';

        return $baseUrl.$this->id.($name ? '/'.Str::slug($name) : '');
    }
}
