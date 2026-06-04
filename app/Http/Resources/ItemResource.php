<?php

namespace App\Http\Resources;

use App\Facades\Blizzard;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Resources\LootCouncil\PriorityResource;
use Exception;
use Illuminate\Database\Eloquent\Model;
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
        $blizzardData = $this->getBlizzardData();

        return [
            'id' => $this->id,
            'raid' => $this->getRelation('raid'),
            'boss' => $this->getRelation('boss'),
            'group' => $this->group,
            'name' => $blizzardData['name'] ?? "Item #{$this->id}",
            'slug' => Str::slug($blizzardData['name'] ?? "item-{$this->id}"),
            'icon' => $this->getFirstMediaUrl('blizzard_icons') ?: null,
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
     */
    protected function getRelation(string $relation): Model|int|null
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
    protected function getBlizzardData(): array
    {
        try {
            return Blizzard::send(new GetItemRequest($this->id))->json();
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Build the Wowhead item URL.
     */
    protected function getWowheadUrl(?string $name = null): string
    {
        $baseUrl = 'https://www.wowhead.com/tbc/item=';

        return $baseUrl.$this->id.($name ? '/'.Str::slug($name) : '');
    }
}
