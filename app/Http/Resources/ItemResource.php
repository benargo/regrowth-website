<?php

namespace App\Http\Resources;

use App\Http\Requests\SearchRequest;
use App\Http\Resources\LootCouncil\CommentResource;
use App\Http\Resources\LootCouncil\PriorityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'group' => $this->whenNotNull($this->group),
            'icon' => $this->getFirstMediaUrl('blizzard_icons') ?: null,
            'inventory_type' => $this->whenHas('inventoryType', fn () => data_get($this, 'inventoryType.name')),
            'item_class' => $this->whenHas('itemClass', fn () => data_get($this, 'itemClass.name')),
            'item_subclass' => $this->whenHas('itemSubclass', fn () => data_get($this, 'itemSubclass.name')),
            'notes' => $this->when(! $request instanceof SearchRequest, fn () => $this->whenNotNull($this->notes)),
            'has_notes' => $this->when($request instanceof SearchRequest, fn () => filled($this->notes)),
            'quality' => $this->whenHas('quality'),
            'quality_border_class' => $this->whenHas('quality', fn () => $this->quality->cssClass('border')),
            'wowhead' => ['url' => $this->wowheadUrl],
            'boss' => $this->whenLoaded('boss', fn () => new BossResource($this->boss)),
            'comments' => $this->whenLoaded(
                'comments',
                fn () => CommentResource::collection($this->comments)->toResponse($request)->getData(true),
            ),
            'comments_count' => $this->whenCounted('comments'),
            'priorities' => $this->whenLoaded('priorities', fn () => PriorityResource::collection($this->priorities)),
            'raid' => $this->whenLoaded('raid', fn () => new RaidResource($this->raid)),
        ];
    }
}
