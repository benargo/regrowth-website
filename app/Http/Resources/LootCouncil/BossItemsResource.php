<?php

namespace App\Http\Resources\LootCouncil;

use App\Facades\Blizzard;
use App\Http\Integrations\Blizzard\Data\Media\MediaData;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Models\LootCouncil\Item;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class BossItemsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'bossId' => $this->resource['bossId'],
            'items' => $this->resource['items']->map(function (Item $item) {
                $blizzardData = $this->getBlizzardData($item);
                $iconUrl = $this->getIconUrl($item);

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
    protected function getBlizzardData(Item $item): array
    {
        try {
            return Blizzard::send(new GetItemRequest($item->id))->json();
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Fetch the item's first icon asset from the Blizzard media endpoint and return its mirrored CDN URL.
     */
    protected function getIconUrl(Item $item): ?string
    {
        try {
            /** @var MediaData $media */
            $media = Blizzard::send(new GetItemMediaRequest($item->id))->dto();

            return ($media->assets[0] ?? null)?->mirroredUrl();
        } catch (ItemNotFoundException) {
            return null;
        }
    }

    protected function getWowheadUrl(Item $item): string
    {
        $baseUrl = 'https://www.wowhead.com/tbc/item=';

        return $baseUrl.$item->id.($item->name ? '/'.Str::slug($item->name) : '');
    }
}
