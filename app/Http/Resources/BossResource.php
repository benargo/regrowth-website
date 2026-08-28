<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BossResource extends JsonResource
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
            'notes' => $this->notes,
            'images' => $this->getMedia()->map->getUrl()->values()->all(),
            'sort_order' => $this->sort_order,
            'raid' => $this->when(
                $this->relationLoaded('raid'),
                fn () => new RaidResource($this->raid),
                $this->raid_id,
            ),
            'items' => $this->whenLoaded('items', fn () => ItemResource::collection($this->items)),
            'comments_count' => $this->whenCounted('comments'),
        ];
    }
}
