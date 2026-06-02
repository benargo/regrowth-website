<?php

namespace App\Http\Resources\LootCouncil;

use App\Facades\Blizzard;
use App\Http\Integrations\Blizzard\Data\Media\MediaData;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
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
        $iconUrl = $this->getIconUrl();

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
    protected function getBlizzardData(): array
    {
        try {
            return Blizzard::send(new GetItemRequest($this->id))->json();
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Fetch a signed icon URL for the item's first media asset.
     */
    protected function getIconUrl(): ?string
    {
        try {
            /** @var MediaData $media */
            $media = Blizzard::send(new GetItemMediaRequest($this->id))->dto();

            $asset = $media->assets[0] ?? null;

            if ($asset === null) {
                return null;
            }

            return URL::signedRoute('icons.show', [
                'size' => 56,
                'name' => (string) Str::of($asset->value)->afterLast('/')->before('?'),
            ]);
        } catch (ItemNotFoundException) {
            return null;
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
